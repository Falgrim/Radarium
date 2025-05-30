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
use App\Services\SendMessageTelegram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendMessageToTelegram extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_chat:send_company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт отправки приветственного сообщения контактам (компаниям) из раздела вакансий';

    /**
     * Execute the console command.
     */
    public function handle(SendMessageTelegram $sendMessageTelegram)
    {
        $users = ApiPostUser::where('is_company', ApiDataTypeEnum::Company)
            ->where('send_welcome_msg', 0)
            ->orderBy('created_at', 'ASC')
            ->get();

        if (!count($users)) {
            $this->info('Нет списка компаний для рассылки');
            //return 0;
        }

        $sendMessageTelegram->send();

        $this->info('Завершено');
    }
}
