<?php

namespace App\Observers;

use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\Specialist;

class ApiChannelObserver
{
    /**
     * После сохранения канала: если изменился регион — обновить регион
     * во всех Builder и Specialist, созданных из постов этого канала.
     */
    public function updated(ApiChannel $channel): void
    {
        if (!$channel->wasChanged('region')) {
            return;
        }

        $postIds = ApiChannelPost::where('api_channel_id', $channel->id)->pluck('id');

        if ($postIds->isEmpty()) {
            return;
        }

        $oldRegion = $channel->getOriginal('region');

        Builder::whereIn('api_channel_post_id', $postIds)
            ->where(function ($q) use ($oldRegion) {
                $q->where('region', $oldRegion)->orWhereNull('region');
            })
            ->update(['region' => $channel->region]);

        Specialist::whereIn('api_channel_post_id', $postIds)
            ->where(function ($q) use ($oldRegion) {
                $q->where('region', $oldRegion)->orWhereNull('region');
            })
            ->update(['region' => $channel->region]);
    }
}
