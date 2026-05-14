<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Models\ApiChannel;
use App\Services\ReadTelegramChats;
use Illuminate\Console\Command;

class ParseTelegramSpecialistChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_parse:specialist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт парсинга чатов ТГ резюме';

    /**
     * Execute the console command.
     */
    public function handle(ReadTelegramChats $readTelegramChats)
    {
        $channels = ApiChannel::where('channel_source', ApiChannelSourceEnum::Telegram)
            ->where('status', ApiChannelStatusEnum::Active)
            ->where('is_company', ApiDataTypeEnum::Specialist)
            ->get();

        if (!count($channels)) {
            $this->info('Нет списка каналов для парсинга');
            return 0;
        }

        $this->info('Подключение к Telegram…');

        $readTelegramChats->readTelegramChannelsWithSharedSession(
            collect($channels),
            function (ReadTelegramChats $svc) {
                foreach ($svc->getInfoMsg() as $item) {
                    $this->info($item);
                }
                foreach ($svc->getWarnMsg() as $item) {
                    $this->warn($item);
                }
                foreach ($svc->getErrorMsg() as $item) {
                    $this->error($item);
                }
            }
        );

        $this->info('Завершено');
    }
}
