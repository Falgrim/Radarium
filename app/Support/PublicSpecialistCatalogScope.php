<?php

declare(strict_types=1);

namespace App\Support;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\ApiPostUser;
use App\Models\Specialist;
use App\Models\UserOpenContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Ограничения публичного каталога проектировщиков ({@see \App\Http\Controllers\CatalogController}) —
 * общий scope для выдачи и ops-команд.
 */
final class PublicSpecialistCatalogScope
{
    /**
     * Специалисты, попадающие в каталог при тех же условиях поиска, что и основной список, без фильтра по region.
     *
     * @param  array<string, mixed>  $validated  key_word, key_word_tags, speciality_id, open_contacts
     * @return Builder<\App\Models\Specialist>
     */
    public static function specialistsMatchingCatalogScope(array $validated, bool $onlyActive = true): Builder
    {
        $query = Specialist::query()
            ->when($onlyActive, function (Builder $q): void {
                $q->where('status', '=', ApiPostAiStatusEnum::Active);
            });

        self::restrictSpecialistCardsToCompleteSourcePosts($query);

        return $query
            ->when(! empty($validated['key_word_tags']), function (Builder $builderQuery) use ($validated) {
                $builderQuery->whereHas('post', function (Builder $postQuery) use ($validated) {
                    $postQuery->where(function (Builder $inner) use ($validated) {
                        foreach ($validated['key_word_tags'] as $keyWordTag) {
                            $inner->orWhere('post', 'like', '%'.$keyWordTag.'%');
                        }
                    });
                });
            })
            ->when(! empty($validated['speciality_id']), function (Builder $builderQuery) use ($validated) {
                $builderQuery->whereRelation('specialities', function (Builder $relationQuery) use ($validated) {
                    $relationQuery->whereIn('dictionary_speciality_id', $validated['speciality_id']);
                });
            })
            ->whereHas('user', function (Builder $userQuery) use ($validated) {
                $userQuery->whereHas('postsComplete', function (Builder $postsQuery) use ($validated) {
                    if (! empty($validated['key_word'])) {
                        $postsQuery->where('post', 'like', '%'.$validated['key_word'].'%');
                    }
                });
                if (! empty($validated['open_contacts']) && Auth::check()) {
                    $userQuery->whereIn('id', function ($sub) {
                        $sub->select('api_post_user_id')
                            ->from(with(new UserOpenContact)->getTable())
                            ->where('user_id', Auth::user()->id);
                    });
                }
                $userQuery->where(function (Builder $contact) {
                    $contact->whereNotNull('phone')
                        ->orWhere('username', '<>', '');
                });
            });
    }

    /**
     * Авторы публичного каталога проектировщиков (без фильтров региона/специализации/ключевых слов).
     *
     * @return Builder<ApiPostUser>
     */
    public static function publicCatalogAuthorsQuery(): Builder
    {
        return ApiPostUser::query()
            ->whereHas('specialists', function (Builder $query): void {
                $query->whereNotNull('api_channel_post_id')
                    ->where('api_channel_post_id', '>', 0)
                    ->where('status', '=', ApiPostAiStatusEnum::Active);
                self::restrictSpecialistCardsToCompleteSourcePosts($query);
            })
            ->where(function (Builder $query) {
                $query->whereNotNull('phone')
                    ->orWhere('username', '<>', '');
            });
    }

    public static function restrictSpecialistCardsToCompleteSourcePosts(Builder $specialistQuery): void
    {
        $specialistQuery->whereHas('post', function (Builder $postQuery): void {
            $postQuery->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
        });
    }
}
