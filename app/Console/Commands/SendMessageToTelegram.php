<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\MailingMessageStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\MailingMessage;
use App\Services\ReadTelegramChats;
use App\Services\SendMessageTelegram;
use Carbon\Carbon;
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
        $settings = Repositories::setting()->getByNames(['mailing_tg_api_id', 'mailing_tg_api_hash', 'mailing_tg_new_company']);

        if (!isset($settings['mailing_tg_api_id']) OR !$settings['mailing_tg_api_id']['value']) {
            $this->info('Не заполнена настройка: mailing_tg_api_id');
            return 0;
        }

        if (!isset($settings['mailing_tg_api_hash']) OR !$settings['mailing_tg_api_hash']['value']) {
            $this->info('Не заполнена настройка: mailing_tg_api_hash');
            return 0;
        }

        $this->info('Проверка сообщений для ручной отправки...');
        $this->sendManualMailing($sendMessageTelegram, $settings);

        $this->info('Проверка сообщений для авто отправки...');
        $this->sendAutoMailing($sendMessageTelegram, $settings);

        $this->info('Завершено');
    }

    protected function sendManualMailing($sendMessageTelegram, $settings)
    {
        $mailing = MailingMessage::where('is_main', 0)
            ->where('status', MailingMessageStatusEnum::ToSend)
            ->where('date_send', '<=', Carbon::now())
            ->orderBy('date_send', 'ASC')
            ->first();

        if (!$mailing) {
            $this->info('Нет сообщения для отправки');
            return false;
        }

        $users = ApiPostUser::where('is_company', ApiDataTypeEnum::Company)
            ->where('send_new_msg', 1)
            ->get();

        if (!count($users)) {
            $this->info('Нет списка компаний для рассылки');
            return false;
        }

        $sendMessageTelegram->send(
            $settings['mailing_tg_api_id']['value'],
            $settings['mailing_tg_api_hash']['value'],
            $mailing,
            $users
        );

        $infoMsg = $sendMessageTelegram->getInfoMsg();
        $warnMsg = $sendMessageTelegram->getWarnMsg();
        $errorMsg = $sendMessageTelegram->getErrorMsg();

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
                Log::channel('mailing_tg')->error('Рассылка ID '.$mailing->id.': '.$item);
                $this->error($item);
            }

            $mailing->status = MailingMessageStatusEnum::Error;
        } else {
            $mailing->status = MailingMessageStatusEnum::Sended;
        }

        $mailing->save();
    }

    protected function sendAutoMailing($sendMessageTelegram, $settings)
    {
        if (!isset($settings['mailing_tg_new_company']) OR !$settings['mailing_tg_new_company']['value']) {
            ApiPostUser::where('is_company', ApiDataTypeEnum::Company)
                ->where('send_welcome_msg', ApiPostUserMailingStatusEnum::ToSend)
                ->update(['send_welcome_msg' => ApiPostUserMailingStatusEnum::Disabled]);

            $this->info('Автоматическая рассылка отключена');
            return false;
        }

        $mailing = MailingMessage::where('is_main', 1)
            ->orderBy('date_send', 'ASC')
            ->first();

        if (!$mailing) {
            $this->info('Нет сообщения для отправки');
            return false;
        }

        $users = ApiPostUser::where('is_company', ApiDataTypeEnum::Company)
            ->where('send_welcome_msg', ApiPostUserMailingStatusEnum::ToSend)
            ->get();

        if (!count($users)) {
            $this->info('Нет списка компаний для рассылки');
            return false;
        }

        $sendMessageTelegram->send(
            $settings['mailing_tg_api_id']['value'],
            $settings['mailing_tg_api_hash']['value'],
            $mailing,
            $users
        );

        $infoMsg = $sendMessageTelegram->getInfoMsg();
        $warnMsg = $sendMessageTelegram->getWarnMsg();
        $errorMsg = $sendMessageTelegram->getErrorMsg();

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
                Log::channel('mailing_tg')->error('Рассылка ID '.$mailing->id.': '.$item);
                $this->error($item);
            }

            $mailing->status = MailingMessageStatusEnum::Error;
        } else {
            $mailing->status = MailingMessageStatusEnum::Sended;
        }

        $mailing->save();
    }
}
