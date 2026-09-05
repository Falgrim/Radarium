<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Jobs\ProcessPendingAiPosts;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Builder as BuilderCard;
use App\Models\CompanyJob;
use App\Models\Specialist;
use Illuminate\Support\Collection;

/**
 * Единая точка повторного прогона сообщений через ИИ: возврат постов в очередь (InQueue)
 * и немедленный запуск фоновой обработки. Используется админкой и командой app:ai_parse:requeue.
 */
final class AiReprocessService
{
    /** Статусы «не обработано ИИ»: обработка не дошла до результата. */
    public const UNPROCESSED_STATUSES = [
        ApiChannelPostStatusEnum::Error,
        ApiChannelPostStatusEnum::Empty,
    ];

    /**
     * Вернуть подходящие посты в очередь ИИ.
     *
     * @param  list<ApiChannelPostStatusEnum>  $statuses
     */
    public function requeue(
        array $statuses = self::UNPROCESSED_STATUSES,
        ?ApiDataTypeEnum $dataType = null,
        ?string $from = null,
        ?string $to = null,
        string $dateField = 'ai_date',
        ?string $provider = null,
        bool $clearMetadata = true,
    ): int {
        $ids = $this->targetIds($statuses, $dataType, $from, $to, $dateField, $provider);

        if ($ids->isEmpty()) {
            return 0;
        }

        $payload = [
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue->value,
            'updated_at' => now(),
        ];

        if ($clearMetadata) {
            $payload['ai_result'] = null;
            $payload['ai_date'] = null;
            $payload['ai_provider_used'] = null;
        }

        $updated = 0;

        foreach ($ids->chunk(500) as $chunk) {
            $chunkIds = $chunk->all();

            $this->deleteCatalogCards($chunkIds);
            $updated += ApiChannelPost::query()->whereIn('id', $chunkIds)->update($payload);
        }

        return $updated;
    }

    /**
     * Идентификаторы постов под условиями отбора (для dry-run и статистики).
     *
     * @param  list<ApiChannelPostStatusEnum>  $statuses
     * @return Collection<int, int>
     */
    public function targetIds(
        array $statuses = self::UNPROCESSED_STATUSES,
        ?ApiDataTypeEnum $dataType = null,
        ?string $from = null,
        ?string $to = null,
        string $dateField = 'ai_date',
        ?string $provider = null,
    ): Collection {
        $query = ApiChannelPost::query()
            ->select('api_channel_posts.id')
            ->whereIn('api_channel_posts.ai_parse_status', $statuses);

        if ($dataType !== null) {
            $query->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
                ->where('api_channels.is_company', $dataType);
        }

        if ($from !== null && $to !== null) {
            $query->whereBetween('api_channel_posts.'.$dateField, [$from, $to]);
        }

        if ($provider !== null && $provider !== '') {
            $query->where('api_channel_posts.ai_provider_used', $provider);
        }

        return $query->pluck('api_channel_posts.id');
    }

    /**
     * Сколько сообщений ждёт обработки ИИ.
     */
    public function pendingCount(?ApiDataTypeEnum $dataType = null): int
    {
        $query = ApiChannelPost::query()
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::InQueue);

        if ($dataType !== null) {
            $query->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
                ->where('api_channels.is_company', $dataType);
        }

        return (int) $query->count();
    }

    /**
     * Запустить фоновую обработку очереди ИИ. Возвращает число сообщений в очереди.
     */
    public function dispatchProcessing(?ApiDataTypeEnum $dataType = null): int
    {
        $pending = $this->pendingCount($dataType);

        if ($pending === 0) {
            return 0;
        }

        $types = $dataType !== null ? [$dataType] : ApiDataTypeEnum::cases();

        foreach ($types as $type) {
            if ($this->pendingCount($type) > 0) {
                ProcessPendingAiPosts::dispatch($type);
            }
        }

        return $pending;
    }

    /**
     * Разбор списка статусов из строки CLI: `error,empty` либо `unprocessed` / `all`.
     *
     * @return list<ApiChannelPostStatusEnum>|null null — некорректное значение
     */
    public function parseStatuses(string $value): ?array
    {
        $value = mb_strtolower(trim($value));

        if ($value === '' || $value === 'unprocessed') {
            return self::UNPROCESSED_STATUSES;
        }

        if ($value === 'all') {
            return [
                ApiChannelPostStatusEnum::Error,
                ApiChannelPostStatusEnum::Empty,
                ApiChannelPostStatusEnum::DontMatch,
                ApiChannelPostStatusEnum::Complete,
            ];
        }

        $map = [
            'inqueue' => ApiChannelPostStatusEnum::InQueue,
            'complete' => ApiChannelPostStatusEnum::Complete,
            'error' => ApiChannelPostStatusEnum::Error,
            'empty' => ApiChannelPostStatusEnum::Empty,
            'dontmatch' => ApiChannelPostStatusEnum::DontMatch,
            'duplicate' => ApiChannelPostStatusEnum::Duplicate,
        ];

        $statuses = [];

        foreach (explode(',', $value) as $name) {
            $name = trim($name);

            if ($name === '' || ! isset($map[$name])) {
                return null;
            }

            $statuses[] = $map[$name];
        }

        return array_values(array_unique($statuses, SORT_REGULAR));
    }

    /**
     * Снять карточки каталога, созданные прошлым прогоном ИИ: иначе они останутся
     * в публичном каталоге, пока пост ждёт повторной обработки.
     *
     * @param  list<int>  $postIds
     */
    private function deleteCatalogCards(array $postIds): void
    {
        BuilderCard::query()->whereIn('api_channel_post_id', $postIds)->delete();
        Specialist::query()->whereIn('api_channel_post_id', $postIds)->delete();
        CompanyJob::query()->whereIn('api_channel_post_id', $postIds)->delete();
    }
}
