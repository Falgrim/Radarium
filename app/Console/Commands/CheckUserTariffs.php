<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\UserTariffStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\UserTariff;
use App\Services\ReadTelegramChats;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckUserTariffs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tariff:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверяет дату завершения тарифа пользователя';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tariffs = UserTariff::where('status', UserTariffStatusEnum::Active)
            ->where('date_end', '<=', Carbon::now())
            ->get();

        if (!count($tariffs)) {
            $this->warn('Просоченные тарифы не найдены');
            return 1;
        }

        foreach ($tariffs as $tariff) {
            $tariff->status = UserTariffStatusEnum::Ended;
            $tariff->save();
        }

        $this->info('Завершено');
    }
}
