<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Перенаправление постов из builder-каналов в каталог проектировщиков,
 * если текст относится к проектированию, визуализации или веб-дизайну.
 */
final class BuilderNonFieldSpecialistRedirect
{
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
     * Удаляет builder-карточку по посту и создаёт specialist.
     *
     * @return array{redirected: bool, import: array{status: string, specialist_id: int|null, message: string|null}|null}
     */
    public function redirectPost(ApiChannelPost $post, string $trigger = 'builder_pipeline'): array
    {
        Builder::where('api_channel_post_id', $post->id)->delete();

        $aiService = $this->createAiServiceForPost($post);
        if ($aiService === null) {
            Log::channel('ai_debug')->warning('[BuilderNonFieldSpecialistRedirect] AI service unavailable', [
                'api_channel_post_id' => $post->id,
                'trigger' => $trigger,
            ]);

            return ['redirected' => false, 'import' => null];
        }

        $import = $this->importer->import($post, $aiService, [
            'trigger' => $trigger,
            'redirected_from' => 'builder_channel',
        ]);

        return [
            'redirected' => $import['status'] === SpecialistPostImporter::STATUS_CREATED,
            'import' => $import,
        ];
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
