<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\Specialist;
use Illuminate\Support\Facades\Log;

/**
 * Перенаправление постов из builder-каналов в каталог проектировщиков,
 * если текст относится к проектированию, визуализации или веб-дизайну.
 *
 * Если у автора уже есть карточка Specialist — новое сообщение дописывается в его корпус,
 * без второй карточки автора. Карточки Builder этого автора удаляются.
 */
final class BuilderNonFieldSpecialistRedirect
{
    public const STATUS_ALREADY_SPECIALIST = 'already_specialist_builders_removed';

    public function __construct(
        private readonly CatalogPublicationBuilderNonServiceSignals $signals,
        private readonly SpecialistPostImporter $importer,
    ) {
    }

    public function shouldRedirect(string $postText): bool
    {
        return $this->signals->shouldRedirectToSpecialist($postText);
    }

    /**
     * Создаёт specialist (если автора ещё нет в проектировщиках) и убирает Builder.
     * Если Specialist уже есть — дописывает это сообщение в корпус автора и убирает Builder.
     *
     * @param  array{fallback_heuristic?: bool, requeue_if_no_ai?: bool}  $options
     * @return array{
     *   redirected: bool,
     *   builders_removed: int,
     *   import: array{status: string, specialist_id: int|null, message: string|null}|null
     * }
     */
    public function redirectPost(ApiChannelPost $post, string $trigger = 'builder_pipeline', array $options = []): array
    {
        $post->loadMissing(['apiPostUser', 'channel.apiAi']);
        $userId = (int) ($post->api_post_user_id ?? 0);

        if ($userId > 0 && $this->authorHasCatalogSpecialist($userId)) {
            $specialistId = $this->attachMessageToExistingAuthor($post, $userId);
            $removed = $this->removeAllBuildersForAuthor($userId);
            Log::channel('ai_debug')->info('[BuilderNonFieldSpecialistRedirect] message attached to existing specialist', [
                'api_channel_post_id' => $post->id,
                'api_post_user_id' => $userId,
                'specialist_id' => $specialistId,
                'builders_removed' => $removed,
                'trigger' => $trigger,
            ]);

            return [
                'redirected' => true,
                'builders_removed' => $removed,
                'import' => [
                    'status' => self::STATUS_ALREADY_SPECIALIST,
                    'specialist_id' => $specialistId,
                    'message' => 'Автор уже в проектировщиках — сообщение добавлено в корпус',
                ],
            ];
        }

        $aiService = $this->createAiServiceForPost($post);
        if ($aiService === null) {
            Log::channel('ai_debug')->warning('[BuilderNonFieldSpecialistRedirect] AI service unavailable', [
                'api_channel_post_id' => $post->id,
                'trigger' => $trigger,
            ]);

            return ['redirected' => false, 'builders_removed' => 0, 'import' => null];
        }

        $fallbackHeuristic = ! ($options['requeue_if_no_ai'] ?? false);

        $import = $this->importer->import($post, $aiService, [
            'trigger' => $trigger,
            'redirected_from' => 'builder_channel',
            'fallback_heuristic' => $fallbackHeuristic,
        ]);

        $removed = 0;
        if ($import['status'] === SpecialistPostImporter::STATUS_CREATED) {
            // Автор теперь в проектировщиках — убираем все его builder-карточки.
            if ($userId > 0) {
                $removed = $this->removeAllBuildersForAuthor($userId);
            } else {
                $removed = Builder::query()
                    ->where('api_channel_post_id', $post->id)
                    ->delete();
            }
        }

        return [
            'redirected' => $import['status'] === SpecialistPostImporter::STATUS_CREATED,
            'builders_removed' => $removed,
            'import' => $import,
        ];
    }

    /**
     * Автор уже есть в проектировщиках: пост становится его сообщением, новая персона не создаётся.
     * Отдельная запись Specialist на этот пост нужна, чтобы сообщение считалось своим в разделе проектирования.
     */
    public function attachMessageToExistingAuthor(ApiChannelPost $post, int $userId): ?int
    {
        $source = Specialist::query()
            ->where('api_post_user_id', $userId)
            ->whereIn('status', [
                ApiPostAiStatusEnum::Active,
                ApiPostAiStatusEnum::InModeration,
            ])
            ->orderByDesc('id')
            ->first();

        $forPost = Specialist::withTrashed()
            ->where('api_channel_post_id', $post->id)
            ->first();

        if ($forPost !== null) {
            if ($forPost->trashed()) {
                $forPost->restore();
            }
            $forPost->api_post_user_id = $userId;
            if ($source !== null) {
                $forPost->status = $source->status;
                $forPost->region = $source->region;
            }
            $forPost->post_date = $post->post_date;
            $forPost->save();
            $specialistId = (int) $forPost->id;
        } elseif ($source !== null) {
            $created = Specialist::query()->create([
                'api_post_user_id' => $userId,
                'api_channel_post_id' => $post->id,
                'status' => $source->status,
                'region' => $source->region,
                'post_date' => $post->post_date,
                'ai_type' => 'предоставление услуги',
                'ai_reason' => 'Сообщение добавлено в корпус существующего проектировщика',
                'contact_info' => '',
            ]);
            $specialistId = (int) $created->id;
        } else {
            $specialistId = null;
        }

        $post->ai_result = json_encode([
            'route' => 'attached_to_existing_specialist',
            'specialist_id' => $specialistId,
            'api_post_user_id' => $userId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $post->ai_date = now();
        $post->ai_parse_status = ApiChannelPostStatusEnum::Complete;
        if ((int) $post->api_post_user_id !== $userId) {
            $post->api_post_user_id = $userId;
        }
        $post->save();

        return $specialistId;
    }

    public function authorHasCatalogSpecialist(int $apiPostUserId): bool
    {
        if ($apiPostUserId <= 0) {
            return false;
        }

        return Specialist::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->whereIn('status', [
                ApiPostAiStatusEnum::Active,
                ApiPostAiStatusEnum::InModeration,
            ])
            ->exists();
    }

    public function firstSpecialistIdForAuthor(int $apiPostUserId): ?int
    {
        $id = Specialist::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->whereIn('status', [
                ApiPostAiStatusEnum::Active,
                ApiPostAiStatusEnum::InModeration,
            ])
            ->orderByDesc('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Soft-delete всех карточек строителей автора.
     */
    public function removeAllBuildersForAuthor(int $apiPostUserId): int
    {
        if ($apiPostUserId <= 0) {
            return 0;
        }

        return (int) Builder::query()
            ->where('api_post_user_id', $apiPostUserId)
            ->delete();
    }

    public function createAiServiceForPost(ApiChannelPost $post): ApiAIYandex|ApiAIOllama|null
    {
        $post->loadMissing(['channel.apiAi']);

        if ($post->channel->apiAi->status !== ApiAiStatusEnum::Active) {
            return null;
        }

        $apiSource = $post->channel->apiAi->api_source;
        $options = $post->channel->apiAi->options;

        if ($apiSource === ApiAiSourceEnum::YandexGTP4) {
            $aiService = new ApiAIYandex;
        } elseif ($apiSource === ApiAiSourceEnum::OllamaQwen) {
            $aiService = new ApiAIOllama;
        } else {
            return null;
        }

        $aiService->setConfig($options);

        return $aiService;
    }
}
