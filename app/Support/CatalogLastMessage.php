<?php

declare(strict_types=1);

namespace App\Support;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiChannelPost;
use App\Models\Builder as BuilderCard;
use App\Models\Specialist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Последнее сообщение автора в публичном каталоге: одно определение и для порядка строк,
 * и для текста в колонке «Последнее сообщение», иначе список расходится со своим содержимым.
 *
 * Дата считается по постам активных карточек витрины, поэтому снятая карточка перестаёт поднимать
 * автора наверх сразу, без пересчёта денормализованного `api_post_users.last_post_date`.
 */
final class CatalogLastMessage
{
    /** Алиас ключа сортировки в выборке авторов. */
    public const SORT_ALIAS = 'catalog_last_post_date';

    /** Ключи, под которыми последнее сообщение подкладывается в модель автора. */
    public const SPECIALIST_RELATION = 'latestSpecialistPost';

    public const BUILDER_RELATION = 'latestBuilderPost';

    /**
     * Коррелированный подзапрос с датой последнего сообщения проектировщика — под `addSelect()` к списку авторов.
     */
    public static function specialistSortKey(): Builder
    {
        return self::latestCompletePostDate(
            self::specialistCards()->whereColumn('specialists.api_post_user_id', 'api_channel_posts.api_post_user_id')
        );
    }

    public static function builderSortKey(): Builder
    {
        return self::latestCompletePostDate(
            self::builderCards()->whereColumn('builders.api_post_user_id', 'api_channel_posts.api_post_user_id')
        );
    }

    /**
     * Кладёт последнее сообщение сразу на всю страницу авторов: иначе каждая строка списка делает свои запросы.
     */
    public static function attachToSpecialistAuthors(LengthAwarePaginator $authors): void
    {
        self::attach(
            $authors,
            self::SPECIALIST_RELATION,
            static fn (array $userIds): Builder => self::specialistCards()->whereIn('api_post_user_id', $userIds)
        );
    }

    public static function attachToBuilderAuthors(LengthAwarePaginator $authors): void
    {
        self::attach(
            $authors,
            self::BUILDER_RELATION,
            static fn (array $userIds): Builder => self::builderCards()->whereIn('api_post_user_id', $userIds)
        );
    }

    /**
     * Посты активных карточек проектировщика (мягко удалённые карточки отсекает глобальный scope модели).
     *
     * @return Builder<Specialist>
     */
    public static function specialistCards(): Builder
    {
        return Specialist::query()
            ->select('api_channel_post_id')
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0);
    }

    /**
     * @return Builder<BuilderCard>
     */
    public static function builderCards(): Builder
    {
        return BuilderCard::query()
            ->select('api_channel_post_id')
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0);
    }

    /**
     * Id сообщений, по которым у автора есть активные карточки проектировщика: в истории сообщений
     * по ним отличают свою витрину от чужой.
     *
     * @return array<int, int>
     */
    public static function specialistCardPostIdsFor(int $userId): array
    {
        return self::postIdsFor(self::specialistCards(), $userId);
    }

    /**
     * @return array<int, int>
     */
    public static function builderCardPostIdsFor(int $userId): array
    {
        return self::postIdsFor(self::builderCards(), $userId);
    }

    /**
     * @param  Builder<Specialist>|Builder<BuilderCard>  $cards
     * @return array<int, int>
     */
    private static function postIdsFor(Builder $cards, int $userId): array
    {
        return $cards
            ->where('api_post_user_id', $userId)
            ->pluck('api_channel_post_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Builder<Specialist>|Builder<BuilderCard>  $cardPostIds
     * @return Builder<ApiChannelPost>
     */
    private static function latestCompletePostDate(Builder $cardPostIds): Builder
    {
        return ApiChannelPost::query()
            ->selectRaw('MAX(api_channel_posts.post_date)')
            ->whereColumn('api_channel_posts.api_post_user_id', 'api_post_users.id')
            ->where('api_channel_posts.ai_parse_status', ApiChannelPostStatusEnum::Complete)
            ->whereIn('api_channel_posts.id', $cardPostIds);
    }

    /**
     * @param  callable(array<int, int>): Builder  $cardPostIds
     */
    private static function attach(LengthAwarePaginator $authors, string $relation, callable $cardPostIds): void
    {
        $rows = $authors->getCollection();
        if ($rows->isEmpty()) {
            return;
        }

        $userIds = $rows->pluck('id')->all();
        $postsTable = (new ApiChannelPost)->getTable();
        $complete = ApiChannelPostStatusEnum::Complete->value;

        $maxDatePerUser = DB::table($postsTable)
            ->select('api_post_user_id', DB::raw('MAX(post_date) as max_post_date'))
            ->where('ai_parse_status', $complete)
            ->whereIn('api_post_user_id', $userIds)
            ->whereIn('id', $cardPostIds($userIds))
            ->groupBy('api_post_user_id');

        // Одна дата может быть у нескольких постов — берём тот же пост, что и запасной путь в модели: с большим id.
        $postIdByUser = DB::query()
            ->from($postsTable.' as p')
            ->joinSub($maxDatePerUser, 'mx', function ($join) {
                $join->on('p.api_post_user_id', '=', 'mx.api_post_user_id')
                    ->on('p.post_date', '=', 'mx.max_post_date');
            })
            ->where('p.ai_parse_status', $complete)
            ->whereIn('p.id', $cardPostIds($userIds))
            ->groupBy('p.api_post_user_id')
            ->select('p.api_post_user_id', DB::raw('MAX(p.id) as post_id'))
            ->pluck('post_id', 'api_post_user_id');

        $posts = $postIdByUser->isEmpty()
            ? collect()
            : ApiChannelPost::query()
                ->whereIn('id', $postIdByUser->values())
                ->get()
                ->keyBy('api_post_user_id');

        foreach ($rows as $author) {
            $author->setRelation($relation, $posts->get($author->id));
        }
    }
}
