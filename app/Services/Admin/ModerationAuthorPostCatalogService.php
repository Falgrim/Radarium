<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\CompanyJob;
use App\Models\Specialist;

/**
 * Действия модератора над сообщением автора: снятие карточек каталога с поста и (опционально) постановка в очередь ИИ.
 */
final class ModerationAuthorPostCatalogService
{
    /**
     * Снять с публикации все карточки каталога, привязанные к этому посту.
     */
    public function disableDomainCardsForPost(ApiChannelPost $post): void
    {
        foreach (Builder::query()->where('api_channel_post_id', $post->id)->get() as $builder) {
            $builder->status = ApiPostAiStatusEnum::Disabled;
            $builder->save();
        }

        foreach (Specialist::query()->where('api_channel_post_id', $post->id)->get() as $specialist) {
            $specialist->status = ApiPostAiStatusEnum::Disabled;
            $specialist->save();
        }

        foreach (CompanyJob::query()->where('api_channel_post_id', $post->id)->get() as $job) {
            $job->status = CompanyJobStatusEnum::Disabled;
            $job->save();
        }
    }

    /**
     * Убрать из каталога (без повторной обработки ИИ).
     */
    public function removeFromCatalog(ApiChannelPost $post): void
    {
        $this->disableDomainCardsForPost($post);
    }

    /**
     * Убрать из каталога и отправить сообщение в очередь парсинга ИИ.
     */
    public function requeueForAi(ApiChannelPost $post): void
    {
        $this->disableDomainCardsForPost($post);
        $post->ai_parse_status = ApiChannelPostStatusEnum::InQueue;
        $post->save();
    }
}
