<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Specialist;
use App\Services\ApiAIOllama;
use App\Services\ApiAIYandex;
use App\Services\AiParsePostExceptionHandler;
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\BuilderVacancyGigHeuristic;
use App\Services\CatalogPublicationGate;
use App\Services\Dictionary;
use App\Services\ModerationAlertService;
use App\Services\RussianRegionNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AiSpecialistPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ai_parse:specialist';

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
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->orderBy('api_channel_posts.post_date', 'asc')
            ->take(100)
            ->get();

        if (! count($posts)) {
            $this->info('Нет списка постов для парсинга');

            return 0;
        }

        $postsAll = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Specialist)
            ->count();

        $this->info('В обработку постов: '.count($posts).' из '.$postsAll);

        $dictionary = new Dictionary;
        $specialityList = $dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Specialist
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
                $post->ai_provider_used = $apiSource->value;
                $result = $aiService->getResult(ApiDataTypeEnum::Specialist);

                if (count($result['json'])) {
                    Specialist::where('api_channel_post_id', $post->id)->delete();

                    $post->ai_result = $result['origin'];
                    $post->ai_date = now();

                    $result['json']['ai_type'] = Str::lower($result['json']['ai_type']);

                    if (
                        $result['json']['ai_type'] != 'резюме' &&
                        $result['json']['ai_type'] != 'предоставление услуги' &&
                        $result['json']['ai_type'] != 'предложение услуг'
                    ) {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();

                        $this->warn('Тип сообщения: '.$result['json']['ai_type']);

                        continue;
                    }

                    $specialistServiceTypes = ['резюме', 'предоставление услуги', 'предложение услуг'];
                    if (
                        in_array($result['json']['ai_type'], $specialistServiceTypes, true)
                        && BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy((string) $post->post)
                    ) {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();
                        $this->warn(
                            'Эвристика подработки/найма: текст похож на набор людей со сменой, post_id='.$post->id.' → DontMatch'
                        );

                        continue;
                    }

                    $result['json']['post_date'] = $post->post_date;
                    $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                    $result['json']['api_channel_post_id'] = $post->id;

                    if (! $result['json']['contact_info']) {
                        $result['json']['contact_info'] = '';
                    }

                    $rawRegion = ! empty($result['json']['location_region'])
                        ? trim((string) $result['json']['location_region'])
                        : trim((string) ($post->channel->region ?? ''));
                    $rawRegion = $rawRegion === '' ? null : $rawRegion;
                    $result['json']['region'] = app(RussianRegionNormalizer::class)->normalize($rawRegion);

                    unset($result['json']['location_region'], $result['json']['location_city']);

                    $specialistSpecialties = $dictionary->checkMatchByList($post->post, $specialityList);

                    $gateDecision = app(CatalogPublicationGate::class)->decide(
                        ApiDataTypeEnum::Specialist,
                        (string) $post->post,
                        array_values($specialistSpecialties),
                        [
                            'api_channel_post_id' => $post->id,
                            'api_channel_id' => $post->api_channel_id,
                            'ai_parser' => self::class,
                        ]
                    );
                    $result['json']['status'] = $gateDecision['status'];
                    if ($gateDecision['reasons'] !== []) {
                        $this->warn(sprintf(
                            'Каталог: без авто-публикации → модерация, post_id=%d. Причины: %s',
                            $post->id,
                            implode(', ', $gateDecision['reasons'])
                        ));
                    }

                    $specialist = Specialist::create($result['json']);

                    if (count($specialistSpecialties)) {
                        $dictionary->updateRelations(
                            DictionaryEnum::Speciality,
                            'specialist',
                            $specialist->id,
                            $specialistSpecialties
                        );
                    }

                    $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                    $post->save();

                    app(AuthorCatalogSpecialitiesSync::class)->syncAfterSpecialistImport((int) $specialist->api_post_user_id);

                    if ($specialist->status === ApiPostAiStatusEnum::InModeration || $specialist->status === ApiPostAiStatusEnum::Active) {
                        $this->info('Создан специалист ID: '.$specialist->id);

                        $moderationAlertService->createAlert(
                            0,
                            ModerationAlertSystemEnum::System,
                            ModerationAlertTableNameEnum::Specialist,
                            $specialist->id,
                            '',
                            $post->id,
                        );
                    } else {
                        $this->info('Данный пост не является типом специалиста');
                    }
                } else {
                    $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                    $post->save();
                    $this->warn('По специалисту не найдены данные');
                }
            } catch (\Throwable $e) {
                app(AiParsePostExceptionHandler::class)->handle(
                    $post,
                    $e,
                    $aiService ?? null,
                    $this
                );
            }
        }

        $this->info('Завершено');
    }
}
