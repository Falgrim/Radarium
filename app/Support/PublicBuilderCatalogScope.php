<?php

declare(strict_types=1);

namespace App\Support;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\UserOpenContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Ограничения публичного каталога строителей ({@see BuilderController}) — общий scope для выдачи и команд бэкапа.
 */
final class PublicBuilderCatalogScope
{
    /**
     * @param  array<string, mixed>  $validated  key_word, key_word_tags, open_contacts (без region).
     * @param  list<int>  $specialityFilterIds
     * @return Builder<\App\Models\Builder>
     */
    public static function buildersMatchingCatalogScope(
        array $validated,
        array $specialityFilterIds,
        bool $onlyActive = true
    ): Builder {
        $query = \App\Models\Builder::query()
            ->whereNotNull('api_channel_post_id')
            ->where('api_channel_post_id', '>', 0)
            ->when($onlyActive, function (Builder $q): void {
                $q->where('status', '=', ApiPostAiStatusEnum::Active);
            });

        self::restrictBuilderCardsToCompleteSourcePosts($query);

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
            ->when($specialityFilterIds !== [], function (Builder $builderQuery) use ($specialityFilterIds) {
                $builderQuery->whereRelation('specialities', function (Builder $relationQuery) use ($specialityFilterIds) {
                    $relationQuery->whereIn('dictionary_speciality_id', $specialityFilterIds);
                });
            })
            ->when(! empty($validated['key_word']), function (Builder $builderQuery) use ($validated) {
                $builderQuery->whereHas('post', function (Builder $postQuery) use ($validated) {
                    $postQuery->where('post', 'like', '%'.$validated['key_word'].'%');
                });
            })
            ->whereHas('user', function (Builder $userQuery) use ($validated) {
                $userQuery->whereDoesntHave('specialists', function (Builder $specialistQuery) {
                    $specialistQuery->where('status', ApiPostAiStatusEnum::Active)
                        ->whereHas('post', function (Builder $postQuery): void {
                            $postQuery->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
                        });
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

    public static function restrictBuilderCardsToCompleteSourcePosts(Builder $builderQuery): void
    {
        $builderQuery->whereHas('post', function (Builder $postQuery): void {
            $postQuery->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
        });
    }
}
