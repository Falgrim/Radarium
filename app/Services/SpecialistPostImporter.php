<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Exceptions\AiProviderUnavailableException;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Создание карточки проектировщика (Specialist) из поста канала — общая логика для
 * app:ai_parse:specialist и перенаправления из builder-пайплайна.
 */
final class SpecialistPostImporter
{
    public const STATUS_CREATED = 'created';

    public const STATUS_DONT_MATCH = 'dont_match';

    public const STATUS_ERROR = 'error';

    public const STATUS_NO_PROMPT = 'no_prompt';

    public const STATUS_AI_UNAVAILABLE = 'ai_unavailable';

    public function __construct(
        private readonly Dictionary $dictionary,
        private readonly CatalogPublicationGate $gate,
        private readonly AuthorCatalogSpecialitiesSync $specialitiesSync,
        private readonly ModerationAlertService $moderationAlerts,
        private readonly AiSystemPromptAdminService $promptAdmin,
        private readonly RussianRegionNormalizer $regionNormalizer,
    ) {
    }

    /**
     * @param  array{trigger?: string, redirected_from?: string}  $context
     * @return array{status: string, specialist_id: int|null, message: string|null}
     */
    public function import(
        ApiChannelPost $post,
        ApiAIYandex|ApiAIOllama $aiService,
        array $context = [],
    ): array {
        $prompt = $this->resolveSpecialistPrompt($post);
        if ($prompt === '') {
            Log::channel('ai_debug')->warning('[SpecialistPostImporter] empty specialist prompt', [
                'api_channel_post_id' => $post->id,
                'api_channel_id' => $post->api_channel_id,
                'context' => $context,
            ]);

            return [
                'status' => self::STATUS_NO_PROMPT,
                'specialist_id' => null,
                'message' => 'Не найден промпт для каталога проектировщиков',
            ];
        }

        $aiService->setPromt($prompt);
        $aiService->setText((string) $post->post);

        try {
            $result = $aiService->getResult(ApiDataTypeEnum::Specialist);
        } catch (AiProviderUnavailableException|ConnectionException $e) {
            Log::channel('ai_debug')->warning('[SpecialistPostImporter] AI provider unavailable', [
                'api_channel_post_id' => $post->id,
                'context' => $context,
                'message' => $e->getMessage(),
            ]);

            if (! empty($context['fallback_heuristic'])) {
                return $this->importHeuristicFallback($post, $context, $e->getMessage());
            }

            $post->ai_parse_status = ApiChannelPostStatusEnum::InQueue;
            $post->save();

            return [
                'status' => self::STATUS_AI_UNAVAILABLE,
                'specialist_id' => null,
                'message' => $e->getMessage(),
            ];
        }

        if (count($result['json']) === 0) {
            $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
            $post->save();

            return [
                'status' => self::STATUS_ERROR,
                'specialist_id' => null,
                'message' => 'ИИ не вернул данные для проектировщика',
            ];
        }

        Specialist::where('api_channel_post_id', $post->id)->delete();

        $payload = $result['json'];
        $payload['ai_type'] = Str::lower($payload['ai_type'] ?? '');

        if (
            $payload['ai_type'] !== 'резюме'
            && $payload['ai_type'] !== 'предоставление услуги'
            && $payload['ai_type'] !== 'предложение услуг'
        ) {
            $post->ai_result = $this->wrapAiResult($result['origin'], $context, [
                'route' => 'dont_match_after_redirect',
                'ai_type' => $payload['ai_type'],
            ]);
            $post->ai_date = now();
            $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
            $post->save();

            return [
                'status' => self::STATUS_DONT_MATCH,
                'specialist_id' => null,
                'message' => 'Тип сообщения после перенаправления: '.$payload['ai_type'],
            ];
        }

        $serviceTypes = ['резюме', 'предоставление услуги', 'предложение услуг'];
        if (
            in_array($payload['ai_type'], $serviceTypes, true)
            && BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy((string) $post->post)
        ) {
            $post->ai_result = $this->wrapAiResult($result['origin'], $context, [
                'route' => 'dont_match_gig_heuristic_after_redirect',
            ]);
            $post->ai_date = now();
            $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
            $post->save();

            return [
                'status' => self::STATUS_DONT_MATCH,
                'specialist_id' => null,
                'message' => 'Эвристика подработки после перенаправления',
            ];
        }

        return $this->persistSpecialist($post, $payload, $context, $this->wrapAiResult($result['origin'], $context, [
            'route' => 'specialist_created',
        ]), false);
    }

    /**
     * Минимальная карточка проектировщика без ИИ (текст поста + специализации из словаря).
     *
     * @param  array{trigger?: string, redirected_from?: string}  $context
     * @return array{status: string, specialist_id: int|null, message: string|null}
     */
    public function importHeuristicFallback(ApiChannelPost $post, array $context, string $aiErrorMessage): array
    {
        if (BuilderVacancyGigHeuristic::shouldOverrideAiServiceToVacancy((string) $post->post)) {
            $post->ai_result = json_encode([
                'specialist_import' => $context,
                'route' => 'dont_match_gig_heuristic_heuristic_fallback',
                'ai_unavailable' => $aiErrorMessage,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $post->ai_date = now();
            $post->ai_parse_status = ApiChannelPostStatusEnum::DontMatch;
            $post->save();

            return [
                'status' => self::STATUS_DONT_MATCH,
                'specialist_id' => null,
                'message' => 'Эвристика подработки (fallback без ИИ)',
            ];
        }

        $payload = $this->buildHeuristicPayload($post);

        return $this->persistSpecialist($post, $payload, $context, json_encode([
            'specialist_import' => $context,
            'route' => 'specialist_created_heuristic_fallback',
            'ai_unavailable' => $aiErrorMessage,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{trigger?: string, redirected_from?: string}  $context
     * @return array{status: string, specialist_id: int|null, message: string|null}
     */
    private function persistSpecialist(
        ApiChannelPost $post,
        array $payload,
        array $context,
        string $aiResultJson,
        bool $heuristicFallback,
    ): array {
        $apiPostUser = $this->resolveOrCreateApiPostUser($post);
        if ($apiPostUser === null) {
            $post->ai_result = $this->wrapAiResult($aiResultJson, $context, [
                'route' => 'error_missing_api_post_user',
            ]);
            $post->ai_date = now();
            $post->ai_parse_status = ApiChannelPostStatusEnum::Error;
            $post->save();

            Log::channel('ai_debug')->warning('[SpecialistPostImporter] missing api_post_user', [
                'api_channel_post_id' => $post->id,
                'user_login_id' => $post->user_login_id,
                'context' => $context,
            ]);

            return [
                'status' => self::STATUS_ERROR,
                'specialist_id' => null,
                'message' => 'Нет автора поста (api_post_user) — карточка не создана',
            ];
        }

        Specialist::where('api_channel_post_id', $post->id)->delete();

        $payload = BuilderNormalizer::sanitizePriceFields($payload);
        $payload['post_date'] = $post->post_date;
        $payload['api_post_user_id'] = $apiPostUser->id;
        $payload['api_channel_post_id'] = $post->id;

        if (empty($payload['contact_info'])) {
            $payload['contact_info'] = '';
        }

        $rawRegion = ! empty($payload['location_region'])
            ? trim((string) $payload['location_region'])
            : trim((string) ($post->channel->region ?? ''));
        $rawRegion = $rawRegion === '' ? null : $rawRegion;
        $payload['region'] = $this->regionNormalizer->normalize($rawRegion);

        unset($payload['location_region'], $payload['location_city']);

        $specialityList = $this->dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Specialist
        );
        $specialistSpecialties = $this->dictionary->checkMatchByList((string) $post->post, $specialityList);

        $gateDecision = $this->gate->decide(
            ApiDataTypeEnum::Specialist,
            (string) $post->post,
            array_values($specialistSpecialties),
            array_merge([
                'api_channel_post_id' => $post->id,
                'api_channel_id' => $post->api_channel_id,
                'ai_parser' => self::class,
                'heuristic_fallback' => $heuristicFallback,
            ], $context)
        );
        $payload['status'] = $gateDecision['status'];

        $specialist = Specialist::create($payload);

        if (count($specialistSpecialties)) {
            $this->dictionary->updateRelations(
                DictionaryEnum::Speciality,
                'specialist',
                $specialist->id,
                $specialistSpecialties
            );
        }

        $wrapExtra = [
            'route' => $heuristicFallback ? 'specialist_created_heuristic_fallback' : 'specialist_created',
            'specialist_id' => $specialist->id,
            'catalog_status' => $gateDecision['status']->value,
            'catalog_gate_reasons' => $gateDecision['reasons'],
        ];
        if ($heuristicFallback) {
            $decoded = json_decode($aiResultJson, true);
            if (is_array($decoded)) {
                $post->ai_result = json_encode(array_merge($decoded, $wrapExtra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $post->ai_result = $aiResultJson;
            }
        } else {
            $decoded = json_decode($aiResultJson, true);
            $post->ai_result = is_array($decoded)
                ? json_encode(array_merge($decoded, $wrapExtra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $this->wrapAiResult($aiResultJson, $context, $wrapExtra);
        }

        $post->ai_date = now();
        $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
        $post->save();

        $this->specialitiesSync->syncAfterSpecialistImport((int) $specialist->api_post_user_id);

        if ($specialist->status === ApiPostAiStatusEnum::InModeration || $specialist->status === ApiPostAiStatusEnum::Active) {
            $this->moderationAlerts->createAlert(
                0,
                ModerationAlertSystemEnum::System,
                ModerationAlertTableNameEnum::Specialist,
                $specialist->id,
                '',
                $post->id,
            );
        }

        Log::channel('ai_debug')->info('[SpecialistPostImporter] specialist created', [
            'api_channel_post_id' => $post->id,
            'specialist_id' => $specialist->id,
            'api_post_user_id' => (int) $specialist->api_post_user_id,
            'context' => $context,
            'catalog_status' => $gateDecision['status']->value,
            'heuristic_fallback' => $heuristicFallback,
        ]);

        return [
            'status' => self::STATUS_CREATED,
            'specialist_id' => (int) $specialist->id,
            'message' => $heuristicFallback ? 'Создано без ИИ (эвристический fallback)' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHeuristicPayload(ApiChannelPost $post): array
    {
        return [
            'ai_type' => 'предоставление услуги',
            'ai_reason' => 'heuristic_redirect_from_builder_channel',
            'experience' => '',
            'soft_experience' => '',
            'education' => '',
            'work_schedule' => '',
            'total_work_project' => '',
            'type_of_work' => '',
            'price_by_hour' => '',
            'price_by_project' => '',
            'price_by_month' => '',
            'about' => trim((string) $post->post),
            'spec_requirements' => '',
            'link_resume' => '',
            'contact_info' => '',
        ];
    }

    public function resolveSpecialistPrompt(ApiChannelPost $post): string
    {
        $apiAi = $post->channel->apiAi;
        $fromPreset = $this->promptAdmin->resolveActivePresetBodyForChannel(ApiDataTypeEnum::Specialist, $apiAi);
        if ($fromPreset !== null) {
            return $fromPreset;
        }

        $apiAiId = (int) ($post->channel->api_ai_id ?? 0);

        $fromSameAi = ApiChannel::query()
            ->where('is_company', ApiDataTypeEnum::Specialist)
            ->when($apiAiId > 0, static function ($query) use ($apiAiId): void {
                $query->where('api_ai_id', $apiAiId);
            })
            ->whereNotNull('ai_promt')
            ->where('ai_promt', '!=', '')
            ->value('ai_promt');

        if (is_string($fromSameAi) && trim($fromSameAi) !== '') {
            return trim($fromSameAi);
        }

        $fromAny = ApiChannel::query()
            ->where('is_company', ApiDataTypeEnum::Specialist)
            ->whereNotNull('ai_promt')
            ->where('ai_promt', '!=', '')
            ->value('ai_promt');

        return is_string($fromAny) ? trim($fromAny) : '';
    }

    /**
     * Находит автора поста или создаёт минимальную запись ApiPostUser по user_login_id.
     * Без автора карточка не попадает в публичную выдачу по авторам.
     */
    private function resolveOrCreateApiPostUser(ApiChannelPost $post): ?ApiPostUser
    {
        $existingId = (int) ($post->api_post_user_id ?? 0);
        if ($existingId > 0) {
            $linked = $post->apiPostUser;
            if ($linked !== null) {
                return $linked;
            }
            $byId = ApiPostUser::query()->find($existingId);
            if ($byId !== null) {
                return $byId;
            }
        }

        $externalUserId = trim((string) ($post->user_login_id ?? ''));
        if ($externalUserId === '' || $externalUserId === '0') {
            return null;
        }

        $channelSource = $post->channel?->channel_source;
        if ($channelSource === null) {
            $channelSource = ApiChannelSourceEnum::Telegram;
        }

        $user = ApiPostUser::query()
            ->where('user_id', $externalUserId)
            ->where('channel_source', $channelSource)
            ->first();

        if ($user === null) {
            $user = ApiPostUser::create([
                'user_id' => $externalUserId,
                'channel_source' => $channelSource,
                'username' => (string) ($post->user_login ?? ''),
                'send_welcome_msg' => ApiPostUserMailingStatusEnum::Waiting,
                'is_company' => ApiDataTypeEnum::Specialist,
            ]);

            Log::channel('ai_debug')->info('[SpecialistPostImporter] created api_post_user stub', [
                'api_channel_post_id' => $post->id,
                'api_post_user_id' => $user->id,
                'user_id' => $externalUserId,
            ]);
        }

        if ((int) $post->api_post_user_id !== (int) $user->id) {
            $post->api_post_user_id = $user->id;
            $post->save();
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function wrapAiResult(string $origin, array $context, array $extra = []): string
    {
        $decoded = json_decode($origin, true);
        $wrapped = array_merge([
            'specialist_import' => $context,
        ], $extra);

        if (is_array($decoded)) {
            $wrapped['ai'] = $decoded;
        } else {
            $wrapped['ai_origin'] = $origin;
        }

        return json_encode($wrapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
