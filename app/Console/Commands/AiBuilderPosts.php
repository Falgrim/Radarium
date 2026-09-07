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
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\ApiAIOllama;
use App\Services\ApiAIYandex;
use App\Services\BuilderAiPromptInjector;
use App\Services\BuilderNormalizer;
use App\Services\BuilderAiPipelineRuntimeConfig;
use App\Services\BuilderServiceOfferClassifier;
use App\Services\BuilderSpecialityMatcher;
use App\Services\AiParsePostExceptionHandler;
use App\Services\BuilderNonFieldSpecialistRedirect;
use App\Services\BuilderVacancyGigHeuristic;
use App\Services\CatalogPublicationBuilderNonServiceSignals;
use App\Services\CatalogPublicationGate;
use App\Services\Dictionary;
use App\Services\ModerationAlertService;
use App\Services\RussianRegionNormalizer;
use App\Services\SpecialistPostImporter;
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
        // Свежие сообщения вперёд: при разборе от старых накопленный архив занимает все 100 мест
        // каждого запуска, и публичный каталог неделями не видит новых постов.
        $posts = ApiChannelPost::select('api_channel_posts.*')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue)
            ->leftJoin(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.is_company', ApiDataTypeEnum::Builder)
            ->orderBy('api_channel_posts.post_date', 'desc')
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

                if ($this->tryRedirectNonFieldToSpecialist($post, 'builder_pipeline_early')) {
                    continue;
                }

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
                    $maxSpec = AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR;
                    $resolved = $matcher->resolve((string) $post->post, $aiSpecialities, $maxSpec);
                    $mergedSpecialities = $resolved['ids'];
                    $fromText = $resolved['log']['text_match_ids'] ?? [];
                    $fromAi = $resolved['log']['ai_match_ids'] ?? [];

                    $rawRegion = ! empty($result['json']['location_region'])
                        ? trim((string) $result['json']['location_region'])
                        : trim((string) ($post->channel->region ?? ''));
                    $rawRegion = $rawRegion === '' ? null : $rawRegion;
                    $result['json']['region'] = app(RussianRegionNormalizer::class)->normalize($rawRegion);

                    $result['json']['post_date'] = $post->post_date;
                    $result['json']['api_post_user_id'] = $post->apiPostUser->id;
                    $result['json']['api_channel_post_id'] = $post->id;

                    $heuristicReasons = (new CatalogPublicationBuilderNonServiceSignals)->reasons((string) $post->post);
                    $blocking = array_values(array_intersect(
                        $heuristicReasons,
                        CatalogPublicationBuilderNonServiceSignals::pipelineHardBlockReasonCodes()
                    ));
                    if ($blocking !== []) {
                        if (in_array(
                            CatalogPublicationBuilderNonServiceSignals::REASON_NON_FIELD_CONSTRUCTION,
                            $blocking,
                            true
                        ) && $this->tryRedirectNonFieldToSpecialist($post, 'builder_pipeline_heuristic')) {
                            continue;
                        }

                        $post->ai_result = json_encode([
                            'heuristic_pipeline_blocked' => true,
                            'reasons' => $heuristicReasons,
                            'blocking_reasons' => $blocking,
                            'route' => 'dont_match_heuristic_pipeline',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $post->ai_date = now();
                        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
                        $post->save();
                        $this->warn(sprintf(
                            'Эвристика пайплайна → DontMatch (карточка не создаётся), post_id=%d, blocking=%s',
                            $post->id,
                            implode(', ', $blocking)
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

                    $builder->refresh();
                    $pivotSpecialityIds = $builder->specialities()
                        ->pluck('dictionary_speciality_id')
                        ->map(static fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    if (count($mergedSpecialities) > $maxSpec) {
                        Log::channel('ai_debug')->warning('[Builder] first-pass speciality count exceeds max', [
                            'post_id' => $post->id,
                            'builder_id' => $builder->id,
                            'api_post_user_id' => (int) $post->apiPostUser->id,
                            'max_specialities' => $maxSpec,
                            'merged_dictionary_speciality_ids' => array_values($mergedSpecialities),
                            'matcher_log' => $resolved['log'] ?? [],
                        ]);
                    }

                    Log::channel('ai_debug')->info('[Builder] post_id='.$post->id, [
                        'raw_type' => $rawType,
                        'normalized_type' => $builderType->value,
                        'builder_id' => $builder->id,
                        'api_post_user_id' => (int) $post->apiPostUser->id,
                        'max_specialities_for_author' => $maxSpec,
                        'first_pass_dictionary_speciality_ids' => array_values($mergedSpecialities),
                        'first_pass_speciality_count' => count($mergedSpecialities),
                        'pivot_dictionary_speciality_ids_after_sync' => $pivotSpecialityIds,
                        'pivot_speciality_count_after_sync' => count($pivotSpecialityIds),
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

                    app(AuthorCatalogSpecialitiesSync::class)->syncAfterBuilderImport((int) $post->apiPostUser->id);
                    $builder->refresh();

                    if ($builder->status === ApiPostAiStatusEnum::InModeration || $builder->status === ApiPostAiStatusEnum::Active) {
                        $this->info('Создан строитель ID: '.$builder->id);
                        $moderationAlertService->createAlert(
                            0,
                            ModerationAlertSystemEnum::System,
                            ModerationAlertTableNameEnum::Builder,
                            $builder->id,
                            '',
                            $post->id,
                        );
                    }
                } else {
                    $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
                    $post->save();
                    $this->warn('По строителю не найдены данные');
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

    private function tryRedirectNonFieldToSpecialist(ApiChannelPost $post, string $trigger): bool
    {
        $redirect = app(BuilderNonFieldSpecialistRedirect::class);
        if (! $redirect->shouldRedirect((string) $post->post)) {
            return false;
        }

        $result = $redirect->redirectPost($post, $trigger);
        $import = $result['import'];

        if ($result['redirected'] && $import !== null) {
            $this->info(sprintf(
                'Перенаправление в проектировщики: post_id=%d → specialist_id=%d (%s)',
                $post->id,
                (int) $import['specialist_id'],
                $trigger
            ));

            return true;
        }

        $message = $import['message'] ?? 'не удалось создать карточку проектировщика';
        $status = $import['status'] ?? 'unknown';

        if ($status === SpecialistPostImporter::STATUS_AI_UNAVAILABLE) {
            $this->warn(sprintf(
                'ИИ недоступен — post_id=%d остаётся InQueue, builder не удалён (%s)',
                $post->id,
                $message
            ));

            return true;
        }

        $post->ai_result = json_encode([
            'redirect_failed' => true,
            'trigger' => $trigger,
            'import_status' => $status,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $post->ai_date = now();
        $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
        $post->save();

        $this->warn(sprintf(
            'Перенаправление в проектировщики не удалось → DontMatch, post_id=%d, trigger=%s, status=%s, %s',
            $post->id,
            $trigger,
            $status,
            $message
        ));

        return true;
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
