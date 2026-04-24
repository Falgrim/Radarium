<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enum\ActiveAuthorsReportTypeEnum;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Enum\CompanyJobStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\Builder;
use App\Models\CompanyJob;
use App\Models\Specialist;
use Illuminate\Support\Str;

final readonly class ActiveAuthorsReportRow
{
    public function __construct(
        public ActiveAuthorsReportTypeEnum $type,
        public ApiPostUser $user,
        public int $domainId,
        public ?int $postId,
        public int $activeCardsCount,
        public string $lastMessage,
        public ?\DateTimeInterface $lastMessagePostDate,
        public ?\DateTimeInterface $domainCreatedAt,
        public string $domainStatusLabel,
        public string $postAiStatusLabel,
        public bool $postAiNotComplete,
    ) {}

    public static function fromBuilder(Builder $builder, ApiPostUser $user, ?ApiChannelPost $post, int $activeCardsCount): self
    {
        $post = $post ?? $builder->post;

        return new self(
            type: ActiveAuthorsReportTypeEnum::Builder,
            user: $user,
            domainId: $builder->id,
            postId: $post?->id,
            activeCardsCount: $activeCardsCount,
            lastMessage: self::truncateMessage($post?->post ?? ''),
            lastMessagePostDate: $post?->post_date,
            domainCreatedAt: $builder->created_at,
            domainStatusLabel: $builder->status instanceof ApiPostAiStatusEnum
                ? (string) ($builder->status->toString() ?? $builder->status->name)
                : '',
            postAiStatusLabel: $post && $post->ai_parse_status instanceof ApiChannelPostStatusEnum
                ? (string) ($post->ai_parse_status->toString() ?? $post->ai_parse_status->name)
                : '—',
            postAiNotComplete: self::isPostAiNotComplete($post),
        );
    }

    public static function fromSpecialist(Specialist $specialist, ApiPostUser $user, ?ApiChannelPost $post, int $activeCardsCount): self
    {
        $post = $post ?? $specialist->post;

        return new self(
            type: ActiveAuthorsReportTypeEnum::Specialist,
            user: $user,
            domainId: $specialist->id,
            postId: $post?->id,
            activeCardsCount: $activeCardsCount,
            lastMessage: self::truncateMessage($post?->post ?? ''),
            lastMessagePostDate: $post?->post_date,
            domainCreatedAt: $specialist->created_at,
            domainStatusLabel: $specialist->status instanceof ApiPostAiStatusEnum
                ? (string) ($specialist->status->toString() ?? $specialist->status->name)
                : '',
            postAiStatusLabel: $post && $post->ai_parse_status instanceof ApiChannelPostStatusEnum
                ? (string) ($post->ai_parse_status->toString() ?? $post->ai_parse_status->name)
                : '—',
            postAiNotComplete: self::isPostAiNotComplete($post),
        );
    }

    public static function fromCompanyJob(CompanyJob $job, ApiPostUser $user, ?ApiChannelPost $post, int $activeCardsCount): self
    {
        $post = $post ?? $job->post;

        return new self(
            type: ActiveAuthorsReportTypeEnum::CompanyJob,
            user: $user,
            domainId: $job->id,
            postId: $post?->id,
            activeCardsCount: $activeCardsCount,
            lastMessage: self::truncateMessage($post?->post ?? ''),
            lastMessagePostDate: $post?->post_date,
            domainCreatedAt: $job->created_at,
            domainStatusLabel: $job->status instanceof CompanyJobStatusEnum
                ? (string) ($job->status->toString() ?? $job->status->name)
                : '',
            postAiStatusLabel: $post && $post->ai_parse_status instanceof ApiChannelPostStatusEnum
                ? (string) ($post->ai_parse_status->toString() ?? $post->ai_parse_status->name)
                : '—',
            postAiNotComplete: self::isPostAiNotComplete($post),
        );
    }

    private static function isPostAiNotComplete(?ApiChannelPost $post): bool
    {
        if (! $post || ! ($post->ai_parse_status instanceof ApiChannelPostStatusEnum)) {
            return false;
        }

        return $post->ai_parse_status !== ApiChannelPostStatusEnum::Complete;
    }

    private static function truncateMessage(string $text): string
    {
        $text = trim($text);

        return Str::limit($text, 500);
    }
}
