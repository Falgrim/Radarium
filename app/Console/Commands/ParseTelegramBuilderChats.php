<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Services\ReadTelegramChats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ParseTelegramBuilderChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_parse:builder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт парсинга чатов ТГ строителей';

    /**
     * Execute the console command.
     */
    public function handle(ReadTelegramChats $readTelegramChats)
    {
        $channels = ApiChannel::where('channel_source', ApiChannelSourceEnum::Telegram)
            ->where('status', ApiChannelStatusEnum::Active)
            ->where('is_company', ApiDataTypeEnum::Builder)
            ->get();

        if (!count($channels)) {
            $this->warn('Нет списка каналов для парсинга');
            return 1;
        }

        foreach ($channels as $channel) {
            $readTelegramChats->read($channel);

            $infoMsg = $readTelegramChats->getInfoMsg();
            $warnMsg = $readTelegramChats->getWarnMsg();
            $errorMsg = $readTelegramChats->getErrorMsg();

            if (count($infoMsg)) {
                foreach ($infoMsg as $item) {
                    $this->info($item);
                }
            }

            if (count($warnMsg)) {
                foreach ($warnMsg as $item) {
                    $this->warn($item);
                }
            }

            if (count($errorMsg)) {
                foreach ($errorMsg as $item) {
                    $this->error($item);
                }
            }
        }

        $this->info('Завершено');
    }
}
