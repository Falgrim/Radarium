<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Services\ReadVkGroups;
use Illuminate\Console\Command;

class ParseVkCompanyChats extends Command
{
    protected $signature = 'app:vk_parse:company';

    protected $description = 'Парсинг стен VK для каналов типа «Вакансия»';

    public function handle(ReadVkGroups $readVkGroups): int
    {
        $channels = ApiChannel::where('channel_source', ApiChannelSourceEnum::VK)
            ->where('status', ApiChannelStatusEnum::Active)
            ->where('is_company', ApiDataTypeEnum::Company)
            ->get();

        if (! count($channels)) {
            $this->info('Нет активных VK-источников для типа «Вакансия»');

            return self::SUCCESS;
        }

        foreach ($channels as $channel) {
            $readVkGroups->read($channel);

            foreach ($readVkGroups->getInfoMsg() as $item) {
                $this->info($item);
            }
            foreach ($readVkGroups->getWarnMsg() as $item) {
                $this->warn($item);
            }
            foreach ($readVkGroups->getErrorMsg() as $item) {
                $this->error($item);
            }
        }

        $this->info('Завершено');

        return self::SUCCESS;
    }
}
