<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\BuilderTypeEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\Specialist;
use App\Models\SpecialistSpeciality;
use App\Services\ApiAIOllama;
use App\Services\ApiAIYandex;
use App\Services\BuilderNormalizer;
use App\Services\Dictionary;
use App\Services\ModerationAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiBuilderPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse:builder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Анализ постов в ИИ для резюме';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $posts = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->orderBy('api_channel_posts.post_date', 'asc')
            ->take(100)
            ->get();

        if (!count($posts)) {
            $this->info('Нет списка постов для парсинга');
            return 0;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        $dictionary = new Dictionary;
        $specialityList = $dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Builder
        );

        $moderationAlertService = app()->make(ModerationAlertService::class);

        foreach ($posts as $post) {
            try {
                $promt = $post->channel->ai_promt;
                $options = $post->channel->apiAi->options;

                $this->info('Анализ поста ID: '.$post->id);
                if ($post->channel->apiAi->status !== ApiAiStatusEnum::Active) {
                    $this->error('ИИ "'.$post->channel->apiAi->title.'" (ID '.$post->channel->apiAi->id.') отключен: '.$post->id);
                    continue;
                }

                $apiSource = $post->channel->apiAi->api_source;

                if ($apiSource === ApiAiSourceEnum::YandexGTP4) {
                    $aiService = new ApiAIYandex;
                } elseif ($apiSource === ApiAiSourceEnum::OllamaQwen) {
                    $aiService = new ApiAIOllama;
                } else {
                    $this->warn('Неизвестный источник');
                    continue;
                }

                $aiService->logging('Анализ поста ID: '.$post->id);
                $aiService->setConfig($options);
                $aiService->setPromt($promt);
                $aiService->setText($post->post);
                $result = $aiService->getResult(ApiDataTypeEnum::Builder);

                $post->ai_provider_used = $apiSource->value;

                if (count($result['json'])) {
                    Builder::where('api_channel_post_id', $post->id)->delete();
                    // ИЗВЕСТНЫЙ РИСК: delete+create теряет ручные правки при повторной обработке.
                    // Stage 2: перейти на updateOrCreate().

                    $post->ai_result = $result['origin'];
                    $post->ai_date   = now();

                    $rawType      = Str::lower($result['json']['ai_type'] ?? '');
                    $builderType  = BuilderNormalizer::normalizeType($rawType);

                    if ($builderType !== BuilderTypeEnum::Service) {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();
                        $this->warn('Тип сообщения: ' . $rawType . ' → DontMatch');
                        continue;
                    }

                    $performerSource = !empty($result['json']['performer_type'])
                        ? $result['json']['performer_type']
                        : ($result['json']['performer_type_raw'] ?? '');
                    $result['json']['performer_type'] = BuilderNormalizer::normalizePerformerType($performerSource);

                    $result['json']['legal_form'] = BuilderNormalizer::normalizeLegalForm(
                        $result['json']['legal_form'] ?? null
                    );

                    if (!empty($result['json']['object_types']) && is_array($result['json']['object_types'])) {
                        $result['json']['object_types'] = BuilderNormalizer::normalizeObjectTypes(
                            $result['json']['object_types']
                        );
                    }

                    if (!empty($result['json']['equipment_skills_json']) && is_array($result['json']['equipment_skills_json'])) {
                        $result['json']['equipment_skills_json'] = BuilderNormalizer::cleanEquipmentSkills(
                            $result['json']['equipment_skills_json']
                        );
                    }

                    $aiSpecialities   = is_array($result['json']['service_types'] ?? null)
                        ? $result['json']['service_types'] : [];
                    $textSpecialities = $dictionary->checkMatchByList($post->post, $specialityList);
                    $aiMatched        = !empty($aiSpecialities)
                        ? $dictionary->matchFromAiList($aiSpecialities, $specialityList) : [];
                    $mergedSpecialities = array_unique(array_merge($aiMatched, $textSpecialities));
                    $result['json']['service_types'] = array_values($mergedSpecialities);

                    $result['json']['region'] =
                        !empty($result['json']['location_region'])
                            ? $result['json']['location_region']
                            : ($post->channel->region ?? null);

                    $result['json']['post_date']           = $post->post_date;
                    $result['json']['api_post_user_id']    = $post->apiPostUser->id;
                    $result['json']['api_channel_post_id'] = $post->id;
                    $result['json']['status']              = ApiPostAiStatusEnum::Active;

                    if (!$result['json']['contact_info']) {
                        $result['json']['contact_info'] = '';
                    }

                    $builder = Builder::create($result['json']);

                    if (count($mergedSpecialities)) {
                        $dictionary->updateRelations(
                            DictionaryEnum::Speciality,
                            'builder',
                            $builder->id,
                            array_values($mergedSpecialities)
                        );
                    }

                    Log::channel('ai_debug')->info('[Builder] post_id=' . $post->id, [
                        'raw_type'           => $rawType,
                        'normalized_type'    => $builderType->value,
                        'specialities_total' => count($mergedSpecialities),
                        'ai_matched'         => count($aiMatched),
                        'text_matched'       => count($textSpecialities),
                        'object_types'       => $result['json']['object_types'] ?? [],
                        'performer_type'     => $result['json']['performer_type'] ?? null,
                        'legal_form'         => $result['json']['legal_form'] ?? null,
                        'location_city'      => $result['json']['location_city'] ?? null,
                    ]);

                    $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                    $post->save();

                    if ($builder->status === ApiPostAiStatusEnum::InModeration || $builder->status === ApiPostAiStatusEnum::Active) {
                        $this->info('Создан строитель ID: ' . $builder->id);
                        $moderationAlertService->createAlert(
                            0,
                            ModerationAlertSystemEnum::System,
                            ModerationAlertTableNameEnum::Builder,
                            $builder->id,
                            ''
                        );
                    }
                } else {
                    $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                    $post->save();
                    $this->warn('По строителю не найдены данные');
                }
            } catch (\TypeError $e) {
                $this->error($e->getMessage());
                if (isset($aiService)) {
                    $aiService->logging($e->getMessage(), true);
                }

                $post->ai_result = $e->getMessage();
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                if (isset($aiService)) {
                    $aiService->logging($e->getMessage(), true);
                }

                $post->ai_result = $e->getMessage();
                $post->ai_date = now();
                $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                $post->save();
            }
        }

        $this->info('Завершено');
    }
}
