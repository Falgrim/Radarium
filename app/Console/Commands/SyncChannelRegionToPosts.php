<?php

namespace App\Console\Commands;

use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\Builder;
use App\Models\Specialist;
use Illuminate\Console\Command;

class SyncChannelRegionToPosts extends Command
{
    protected $signature = 'app:sync_channel_region_to_posts';

    protected $description = 'Однократная синхронизация: проставить регион канала во все Builder и Specialist, созданные из постов этого канала';

    public function handle(): int
    {
        $channels = ApiChannel::whereNotNull('region')->cursor();
        $updatedBuilders = 0;
        $updatedSpecialists = 0;

        foreach ($channels as $channel) {
            $postIds = ApiChannelPost::where('api_channel_id', $channel->id)->pluck('id');
            if ($postIds->isEmpty()) {
                continue;
            }

            $b = Builder::whereIn('api_channel_post_id', $postIds)
                ->whereNull('region')
                ->update(['region' => $channel->region]);

            $s = Specialist::whereIn('api_channel_post_id', $postIds)
                ->whereNull('region')
                ->update(['region' => $channel->region]);

            $updatedBuilders += $b;
            $updatedSpecialists += $s;
        }

        $this->info("Обновлено записей Builder: {$updatedBuilders}, Specialist: {$updatedSpecialists}.");
        return self::SUCCESS;
    }
}
