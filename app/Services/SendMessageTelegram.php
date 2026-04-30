<?php

namespace App\Services;

use Amp\Ipc\Sync\ChannelException;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Enum\MailingMessageLogStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use App\Models\MailingMessage;
use App\Models\MailingMessageLog;
use danog\MadelineProto\PeerNotInDbException;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendMessageTelegram
{
    private ApiChannel $apiChannel;

    protected $cronCountPosts;
    protected $minLengthPost;

    protected array $messages = [
        'info' => [],
        'warn' => [],
        'error' => [],
    ];

    public function __construct() {
    }

    private function unsetMessages()
    {
        $this->messages = [
            'info' => [],
            'warn' => [],
            'error' => [],
        ];
    }

    public function send(string $apiId, string $apiHash, MailingMessage $mailingMessage, Collection $users): bool
    {
        $this->unsetMessages();

        if (!isset($apiId) OR !isset($apiHash)) {
            $this->setWarnMsg('Нет конфигурации бота для чата: нет api_id и/или api_hash');
            return false;
        }

        $settings = new Settings;
        $settings->setAppInfo(
            (new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId($apiId)
                ->setApiHash($apiHash)
                ->setLangCode('RU')
        );

        if (config('database.redis.default.password')) {
            $settings->setDb(
                (new \danog\MadelineProto\Settings\Database\Redis)
                    ->setUri('redis://' . config('database.redis.default.host'))
                    ->setPassword(config('database.redis.default.password'))
            );
        } else {
            $settings->setDb(
                (new \danog\MadelineProto\Settings\Database\Redis)
                    ->setUri('redis://' . config('database.redis.default.host'))
            );
        }

        MadelineConnectionConfigurator::apply($settings);
        MadelineConnectionConfigurator::applyFileLogger($settings);

        $MadelineProto = new \danog\MadelineProto\API('session.madeline.'.$apiId, $settings);

        if (!$MadelineProto->getSelf()) {
            $MadelineProto->start();
        }

        $stats = [
            'success' => 0,
            'errors' => 0,
        ];

        foreach ($users as $user) {
            if ($mailingMessage->is_main) {
                $user->send_welcome_msg = ApiPostUserMailingStatusEnum::Error;
            } else {
                $user->send_new_msg = 0;
            }

            $id = 0;
            $status = MailingMessageLogStatusEnum::Error;

            try {
                // https://docs.madelineproto.xyz/PHP/danog/MadelineProto/API.html#sendMessage
                $sentMessage = $MadelineProto->messages->sendMessage([
                    'peer' => $user->username ? '@'.$user->username : $user->user_id,
                    'message' => $mailingMessage->text,
                    'no_webpage' => true,
                ]);

                $id = $sentMessage['id'] ?? 0;
                $status = MailingMessageLogStatusEnum::Success;

                $this->setInfoMsg('UserID ['.$user->id.'] MID ['.$mailingMessage->id.']: Сообщение отправлено');
                $stats['success'] ++;

                if ($mailingMessage->is_main) {
                    $user->send_welcome_msg = ApiPostUserMailingStatusEnum::Sended;
                }
            } catch (RPCErrorException $e) {
                $this->setErrorMsg('UserID ['.$user->id.'] MID ['.$mailingMessage->id.'] RPCErrorException: '.$e->getMessage());
                $stats['errors'] ++;
            } catch (PeerNotInDbException $e) {
                $this->setErrorMsg('UserID ['.$user->id.'] MID ['.$mailingMessage->id.'] PeerNotInDbException: '.$e->getMessage());
                $stats['errors'] ++;
            } catch (\Exception $e) {
                $this->setErrorMsg('UserID ['.$user->id.'] MID ['.$mailingMessage->id.'] Exception: '.$e->getMessage());
                $stats['errors'] ++;
            }

            $user->save();

            MailingMessageLog::create([
                'mailing_message_id' => $mailingMessage->id,
                'api_post_user_id' => $user->id,
                'msg_id' => $id,
                'status' => $status,
            ]);
        }

        Log::channel('mailing_tg')->info('MID ['.$mailingMessage->id.'] Всего чатов: '.count($users).'; Доставлено: '.$stats['success'].'; Ошибок: '.$stats['errors']);
        $this->setInfoMsg('MID ['.$mailingMessage->id.'] Всего чатов: '.count($users).'; Доставлено: '.$stats['success'].'; Ошибок: '.$stats['errors']);

        if ($MadelineProto) {
            try {
                unset($MadelineProto);
            } catch (\Throwable $e) {
                $this->setErrorMsg('Shutdown error: ' . $e->getMessage());
            }
        }

        gc_collect_cycles();

        return true;
    }

    private function setInfoMsg(string $text)
    {
        $this->messages['info'][] = $text;
    }

    public function getInfoMsg(): array
    {
        return $this->messages['info'];
    }

    private function setWarnMsg(string $text)
    {
        $this->messages['warn'][] = $text;
    }

    public function getWarnMsg(): array
    {
        return $this->messages['warn'];
    }

    private function setErrorMsg(string $text)
    {
        $this->messages['error'][] = $text;
    }

    public function getErrorMsg(): array
    {
        return $this->messages['error'];
    }
}
