<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ModerationAlertSystemEnum;
use App\Enum\ModerationAlertTableNameEnum;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Specialist;
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

        $specialityList = $this->dictionary->getAll(
            DictionaryEnum::Speciality,
            ApiDataTypeEnum::Specialist
        );

        $aiService->setPromt($prompt);
        $aiService->setText((string) $post->post);
        $result = $aiService->getResult(ApiDataTypeEnum::Specialist);

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

        $payload['post_date'] = $post->post_date;
        $payload['api_post_user_id'] = $post->apiPostUser->id;
        $payload['api_channel_post_id'] = $post->id;

        if (! $payload['contact_info']) {
            $payload['contact_info'] = '';
        }

        $rawRegion = ! empty($payload['location_region'])
            ? trim((string) $payload['location_region'])
            : trim((string) ($post->channel->region ?? ''));
        $rawRegion = $rawRegion === '' ? null : $rawRegion;
        $payload['region'] = $this->regionNormalizer->normalize($rawRegion);

        unset($payload['location_region'], $payload['location_city']);

        $specialistSpecialties = $this->dictionary->checkMatchByList((string) $post->post, $specialityList);

        $gateDecision = $this->gate->decide(
            ApiDataTypeEnum::Specialist,
            (string) $post->post,
            array_values($specialistSpecialties),
            array_merge([
                'api_channel_post_id' => $post->id,
                'api_channel_id' => $post->api_channel_id,
                'ai_parser' => self::class,
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

        $post->ai_result = $this->wrapAiResult($result['origin'], $context, [
            'route' => 'specialist_created',
            'specialist_id' => $specialist->id,
            'catalog_status' => $gateDecision['status']->value,
            'catalog_gate_reasons' => $gateDecision['reasons'],
        ]);
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
        ]);

        return [
            'status' => self::STATUS_CREATED,
            'specialist_id' => (int) $specialist->id,
            'message' => null,
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
