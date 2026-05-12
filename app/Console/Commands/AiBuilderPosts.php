<?php

namespace App\Console\Commands;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\BuilderTypeEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Services\ApiAIOllama;
use App\Services\ApiAIYandex;
use App\Services\BuilderAiPromptInjector;
use App\Services\BuilderNormalizer;
use App\Services\BuilderAiPipelineRuntimeConfig;
use App\Services\BuilderServiceOfferClassifier;
use App\Services\BuilderSpecialityMatcher;
use App\Services\BuilderVacancyGigHeuristic;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use App\Services\CatalogPublicationGate;
use App\Services\Dictionary;
use App\Services\ModerationAlertService;
use App\Services\RussianRegionNormalizer;
use Illuminate\Console\Command;
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

        if (! count($posts)) {
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
        $pipelineConfig = app(BuilderAiPipelineRuntimeConfig::class);

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
                $post->ai_provider_used = $apiSource->value;

                if (
                    $apiSource === ApiAiSourceEnum::OllamaQwen
                    && $pipelineConfig->twoPassOllamaEnabled()
                    && $aiService instanceof ApiAIOllama
                ) {
                    $pass1 = app(BuilderServiceOfferClassifier::class)->classify(
                        $aiService,
                        (string) $post->post
                    );

                    $aiService->logging([
                        'builder_pass1' => [
                            'api_channel_post_id' => $post->id,
                            'type' => $pass1['type'],
                            'source' => $pass1['source'],
                            'reason' => $pass1['reason'],
                        ],
                    ]);

                    if ($pass1['type'] !== BuilderServiceOfferClassifier::TYPE_SERVICE) {
                        $post->ai_result = json_encode([
                            'pass1' => $pass1,
                            'route' => 'dont_match',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $post->ai_date = now();
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();

                        $this->warn(sprintf(
                            'Pass 1: мусор → DontMatch, post_id=%d, source=%s, reason=%s',
                            $post->id,
                            $pass1['source'],
                            $pass1['reason'] ?? ''
                        ));

                        continue;
                    }

                    $aiService->setGenerationOptions();
                }

                $promt = trim((string) $promt);
                if ($promt === '') {
                    $promt = $pipelineConfig->pass2DefaultPrompt();
                }

                $promtWithSpecialities = BuilderAiPromptInjector::injectSpecialitiesList($promt, $specialityList);
                $aiService->setPromt($promtWithSpecialities);
                $aiService->setText($post->post);
                $result = $aiService->getResult(ApiDataTypeEnum::Builder);

                if (count($result['json'])) {
                    Builder::where('api_channel_post_id', $post->id)->delete();
                    // ИЗВЕСТНЫЙ РИСК: delete+create теряет ручные правки при повторной обработке.
                    // Stage 2: перейти на updateOrCreate().

                    $post->ai_result = $result['origin'];
                    $post->ai_date = now();

                    $rawType = Str::lower($result['json']['ai_type'] ?? '');
                    $result['json']['ai_type'] = $rawType;

                    $serviceLikeTypes = [
                        'предложение услуги',
                        'предложение услуг',
                        'предоставление услуги',
                        'резюме',
                    ];
                    $typeBeforeHeuristic = $rawType;
                    if (
                        in_array($rawType, $serviceLikeTypes, true)
                        && BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy((string) $post->post)
                    ) {
                        $rawType = 'вакансия';
                        $result['json']['ai_type'] = 'вакансия';

                        Log::channel('builder_type_override')->info('Эвристика подработки: тип изменён', [
                            'processed_at' => now()->format('Y-m-d H:i:s'),
                            'post_date' => $post->post_date?->format('Y-m-d H:i:s'),
                            'api_channel_post_id' => $post->id,
                            'api_channel_id' => $post->api_channel_id,
                            'ai_type_in' => $typeBeforeHeuristic,
                            'ai_type_out' => $rawType,
                            'post_text' => $this->truncateForOverrideLog((string) $post->post),
                        ]);

                        $this->warn('Эвристика подработки/найма: тип скорректирован на «вакансия», post_id='.$post->id);
                    }

                    $builderType = BuilderNormalizer::normalizeType($rawType);

                    if ($builderType !== BuilderTypeEnum::Service) {
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();
                        $this->warn('Тип сообщения: '.$rawType.' → DontMatch');

                        continue;
                    }

                    $performerSource = ! empty($result['json']['performer_type'])
                        ? $result['json']['performer_type']
                        : ($result['json']['performer_type_raw'] ?? '');
                    $result['json']['performer_type'] = BuilderNormalizer::normalizePerformerType($performerSource);

                    $result['json']['legal_form'] = BuilderNormalizer::normalizeLegalForm(
                        $result['json']['legal_form'] ?? null
                    );

                    if (! empty($result['json']['object_types']) && is_array($result['json']['object_types'])) {
                        $result['json']['object_types'] = BuilderNormalizer::normalizeObjectTypes(
                            $result['json']['object_types']
                        );
                    }

                    if (! empty($result['json']['equipment_skills_json']) && is_array($result['json']['equipment_skills_json'])) {
                        $result['json']['equipment_skills_json'] = BuilderNormalizer::cleanEquipmentSkills(
                            $result['json']['equipment_skills_json']
                        );
                    }

                    $aiSpecialities = [];
                    if (is_array($result['json']['service_types'] ?? null)) {
                        $aiSpecialities = array_merge($aiSpecialities, $result['json']['service_types']);
                    }
                    if (is_array($result['json']['specialities'] ?? null)) {
                        $aiSpecialities = array_merge($aiSpecialities, $result['json']['specialities']);
                    }
                    $aiSpecialities = array_values(array_unique(array_filter(array_map('strval', $aiSpecialities))));

                    $matcher = app(BuilderSpecialityMatcher::class);
                    $resolved = $matcher->resolve((string) $post->post, $aiSpecialities);
                    $mergedSpecialities = $resolved['ids'];
                    $fromText = $resolved['log']['text_match_ids'] ?? [];
                    $fromAi = $resolved['log']['ai_match_ids'] ?? [];

                    $rawRegion = ! empty($result['json']['location_region'])
                        ? trim((string) $result['json']['location_region'])
                        : trim((string) ($post->channel->region ?? ''));
                    $rawRegion = $rawRegion === '' ? null : $rawRegion;
                    $result['json']['region'] = app(RussianRegionNormalizer::class)->normalizeOrKeep($rawRegion);

                    $result['json']['post_date'] = $post->post_date;
                    $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                    $result['json']['api_channel_post_id'] = $post->id;

                    $hiringReasons = (new CatalogPublicationBuilderNonServiceSignals)->reasons((string) $post->post);
                    if (in_array(CatalogPublicationBuilderNonServiceSignals::REASON_HIRING, $hiringReasons, true)) {
                        $post->ai_result = json_encode([
                            'hiring_text_blocked' => true,
                            'reasons' => $hiringReasons,
                            'route' => 'dont_match_hiring_heuristic',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $post->ai_date = now();
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();
                        $this->warn(sprintf(
                            'Текст по эвристике найма → DontMatch (карточка не создаётся), post_id=%d, reasons=%s',
                            $post->id,
                            implode(', ', $hiringReasons)
                        ));

                        continue;
                    }

                    $gateDecision = app(CatalogPublicationGate::class)->decide(
                        ApiDataTypeEnum::Builder,
                        (string) $post->post,
                        $mergedSpecialities,
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

                    if (! $result['json']['contact_info']) {
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

                    Log::channel('ai_debug')->info('[Builder] post_id='.$post->id, [
                        'raw_type' => $rawType,
                        'normalized_type' => $builderType->value,
                        'specialities_total' => count($mergedSpecialities),
                        'catalog_status' => $gateDecision['status']->value,
                        'catalog_gate_reasons' => $gateDecision['reasons'],
                        'ai_matched' => count($fromAi),
                        'text_matched' => count($fromText),
                        'speciality_match_log' => $resolved['log'] ?? [],
                        'object_types' => $result['json']['object_types'] ?? [],
                        'performer_type' => $result['json']['performer_type'] ?? null,
                        'legal_form' => $result['json']['legal_form'] ?? null,
                        'location_city' => $result['json']['location_city'] ?? null,
                    ]);

                    $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
                    $post->save();

                    if ($builder->status === ApiPostAiStatusEnum::InModeration || $builder->status === ApiPostAiStatusEnum::Active) {
                        $this->info('Создан строитель ID: '.$builder->id);
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

    /**
     * Ограничение длины текста поста в логе переопределения типа (Monolog/Laravel строка одной записи).
     */
    private function truncateForOverrideLog(string $text, int $maxChars = 20000): string
    {
        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, $maxChars)."\n… [обрезано, всего символов: ".mb_strlen($text).']';
    }
}
