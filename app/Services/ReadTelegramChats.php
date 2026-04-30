<?php

namespace App\Services;

use Amp\Ipc\Sync\ChannelException;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use danog\MadelineProto\PeerNotInDbException;
use danog\MadelineProto\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use danog\MadelineProto\Logger;

class ReadTelegramChats
{
    private ApiChannel $apiChannel;

    protected $cronCountPosts;
    protected $minLengthPost;

    protected array $messages = [
        'info' => [],
        'warn' => [],
        'error' => [],
    ];

    public const PHOTO_PATH = 'telegram/profile_photos';

    public function __construct() {
        $this->cronCountPosts = Repositories::setting()->findByName('cron_count_posts');
        $this->minLengthPost = Repositories::setting()->findByName('min_length_post');
    }

    private function unsetMessages()
    {
        $this->messages = [
            'info' => [],
            'warn' => [],
            'error' => [],
        ];
    }

    public function read(ApiChannel $apiChannel): array
    {
        $this->unsetMessages();
        $this->apiChannel = $apiChannel;
        
               // --- Лимитируем размер лог-файла MadelineProto ---
        $logPath = storage_path('logs/MadelineProto.log');
        $maxLogSize = 10 * 1024 * 1024; // 10 МБ
        if (file_exists($logPath) && filesize($logPath) > $maxLogSize) {
            file_put_contents($logPath, ''); // очищаем файл
        }
        // --- END ---


        if (!count($this->apiChannel->options) OR !isset($this->apiChannel->options['api_id']) OR !isset($this->apiChannel->options['api_hash'])) {
            $this->setWarnMsg('Канал ID'.$this->apiChannel->id.': нет api_id и/или api_hash');
            return [];
        }

        $settings = new Settings;
        $settings->setAppInfo(
            (new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId($this->apiChannel->options['api_id'])
                ->setApiHash($this->apiChannel->options['api_hash'])
                ->setLangCode('RU')
        );

        MadelineConnectionConfigurator::applyFileLogger($settings, Logger::ULTRA_VERBOSE);

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

        $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
        // Не вызывать updateSettings сразу после конструктора: API уже ставит в очередь merge
        // полного $settings при подключении IPC; лишний sync-вызов даёт гонку (MTProto::$logger до init).

        if (!$MadelineProto->getSelf()) {
            $MadelineProto->start();
        }

        if (!$this->apiChannel->last_date_check) {
            $offsetDate = $this->apiChannel->post_from_date ? $this->apiChannel->post_from_date : '2025-01-01 00:00:00';
        } else {
            $offsetDate = $this->apiChannel->last_date_check;
        }

        $params = [
            'peer'          => $this->apiChannel->link,
            'limit'         => $this->cronCountPosts?->value ?? 100,
            'offset_date'   => strtotime($offsetDate),
        ];

        $this->setInfoMsg('Выборка с даты: '.date('H:i:s d.m.Y', $params['offset_date']));

        try {
            $messages = $MadelineProto->messages->getHistory($params);
        } catch (ChannelException $e) {
            $this->setErrorMsg('ChannelException: ' . $e->getMessage());
            unset($MadelineProto);
            return [];
        } catch (\Exception $e) {
            $this->setErrorMsg('Exception: ' . $e->getMessage());
            unset($MadelineProto);
            return [];
        }

        /* Сообщения, сортировка по дате (новые сверху) */
        $messages = array_reverse($messages['messages']);

        // Структура для reply_to https://docs.madelineproto.xyz/API_docs/constructors/messageReplyHeader.html
        $messagesOrigin = $messages;
        $countMsg = count($messages);

        // Для дебага
        /*foreach ($messages as $key => $message) {
            if ($this->apiChannel->last_post_id AND $this->apiChannel->last_post_id >= $message['id']) {
                continue;
            }

            if (isset($message['media'])) {
                unset($message['media']);
            }
            echo (new \DateTime())->setTimestamp($message['date'])->format("Y-m-d H:i:s").PHP_EOL;
        }*/

        $this->checkReplyTo($messages);
        $this->checkMinLength($messages);

        $addedCount = 0;

        if ($countMsgFiltered = count($messages)) {
            foreach ($messages as $message) {
                if ($this->apiChannel->last_post_id AND $this->apiChannel->last_post_id >= $message['id']) {
                    continue;
                }

                $postCheck = ApiChannelPost::where('post_id', '!=', $message['id'])
                    ->where('post', trim($message['message']))
                    ->where('api_channel_id', $this->apiChannel->id)
                    ->orderByDesc('post_date')
                    ->first();

                if (!is_null($postCheck)) {
                    $this->setWarnMsg('Дубликат: '.$message['id'].' (БД '.$postCheck->id.')');
                }

                try {
                    $this->setInfoMsg('Add ID: ' . $message['id']);
                    $userInfo = $MadelineProto->getInfo($message['from_id']);
                } catch (PeerNotInDbException $e) {
                    $this->setErrorMsg($e->getMessage());
                }

                $userData = [];
                if (isset($userInfo['User'])) {
                    $userData = [
                        'first_name'    => $userInfo['User']['first_name'] ?? null,
                        'last_name'     => $userInfo['User']['last_name'] ?? null,
                        'username'      => $userInfo['User']['username'] ?? '',
                        'user_id'       => $userInfo['user_id'],
                        'user_type'     => $userInfo['type'],
                        'phone'         => $userInfo['User']['phone'] ?? null,
                        'last_online'   => isset($userInfo['User']['status']['was_online']) ? (new \DateTime())->setTimestamp($userInfo['User']['status']['was_online'])->format("Y-m-d H:i:s") : null,
                    ];
                } else {
                    $this->setWarnMsg('Не получена информация по пользователю');
                }

                if (count($userData)) {

                    $user = ApiPostUser::where('user_id', $message['from_id'])
                        ->where('channel_source', $this->apiChannel->channel_source)
                        ->first();

                    if (!$user) {
                        $user = ApiPostUser::create([
                            'user_id' => $message['from_id'],
                            'channel_source' => $this->apiChannel->channel_source,
                            'send_welcome_msg' => ApiPostUserMailingStatusEnum::Waiting,
                            'first_name' => $userData['first_name'],
                            'username' => $userData['username'],
                            'user_type' => $userData['user_type'],
                            'phone' => $userData['phone'],
                            'last_online_date' => $userData['last_online'],
                        ]);

                        $this->setInfoMsg('Создан новый пользователь: '.$user->id);
                    } else {
                        $user->first_name = $userData['first_name'];
                        $user->username = $userData['username'];
                        $user->user_type = $userData['user_type'];
                        $user->phone = $userData['phone'];
                        $user->last_online_date = $userData['last_online'];

                        $user->save();

                        $this->setInfoMsg('Обновлен пользователь: ' . $user->id);
                    }

                    if (isset($userInfo['User']['photo'])) {
                        $photos = $MadelineProto->photos->getUserPhotos([
                            'user_id' => $userData['user_id'],
                            'offset' => 0,
                            'max_id' => 0,
                            'limit' => 1,
                        ]);

                        try {
                            $photoPath = $this->downloadUserPhoto($MadelineProto, $user, $photos['photos'] ?? []);
                            if ($photoPath !== false) {
                                ApiPostUser::where('id', $user->id)->update(['photo' => $photoPath]);
                            }
                        } catch (\Exception $e) {
                            $this->setWarnMsg('Не удалось скачать фото профиля ID' . $user->id.': '.$e->getMessage());
                        }
                    }
                }

                $post = ApiChannelPost::updateOrCreate([
                    'api_channel_id' => $this->apiChannel->id,
                    'post_id' => $message['id']
                ], [
                    'api_post_user_id'  => $user?->id ?? 0,
                    'api_channel_id'    => $this->apiChannel->id,
                    'user_login'        => $userData['username'] ?? '',
                    'user_login_id'     => $message['from_id'],
                    'post_id'           => $message['id'],
                    'post_date'         => Carbon::createFromTimestamp($message['date'])->toDateTimeString(),
                    'post'              => trim($message['message']),
                    'ai_parse_status'   => is_null($postCheck) ? ApiChannelPostStatusEnum::InQueue : ApiChannelPostStatusEnum::Duplicate,
                ]);

                if ($post->wasRecentlyCreated === true) {
                    $addedCount ++;
                    $this->setInfoMsg('Создан новый пост: ' . $post->id);
                } else {
                    $this->setInfoMsg('Обновлен пост: ' . $post->id);
                }
            }
        }

        // Если есть хоть какие-то сообщения - обновляем дату сканирования
        if (count($messagesOrigin)) {
            $lastPostId = last($messagesOrigin)['id'] ?? null;
            $lastDate = last($messagesOrigin)['date'] ?? null;

            if (!is_null($lastPostId)) {
                if (!$this->apiChannel->last_post_id OR $this->apiChannel->last_post_id < $lastPostId) {
                    $this->apiChannel->last_post_id = $lastPostId;
                }

                $lastDate = (new \DateTime())->setTimestamp($lastDate)->format("Y-m-d H:i:s");
                if (!$this->apiChannel->last_date_check) {
                    $this->apiChannel->last_date_check = $lastDate;
                } elseif($this->apiChannel->last_date_check->lessThan($lastDate)) {
                    $this->apiChannel->last_date_check = $lastDate;
                } else {
                    $lastDate = $this->apiChannel->last_date_check->addDays(3);
                    if (!$lastDate->greaterThan(Carbon::now())) {
                        $this->apiChannel->last_date_check = $this->apiChannel->last_date_check->addDays(3);
                    } else {
                        $this->apiChannel->last_date_check = Carbon::now();
                    }
                }

                if ($this->apiChannel->isDirty()) {
                    $this->apiChannel->save();
                }
            }
        } elseif (!$this->apiChannel->last_date_check OR !$this->apiChannel->last_post_id) {
            $this->apiChannel->last_date_check = Carbon::parse($offsetDate)->addDays(3);
            $this->apiChannel->save();
        }

        Log::channel('post_parser')->info($apiChannel->id.': Всего постов '.$countMsg.'; Отфильтровано '.$countMsgFiltered.'; Новых '.$addedCount);
        $this->setInfoMsg('Прочитано '.$countMsg.'; Допущенных: '.$countMsgFiltered);
        $this->setInfoMsg('Последний ID: '.$this->apiChannel->last_post_id);
        $this->setInfoMsg('Последняя дата: '.$this->apiChannel->last_date_check);

        if ($MadelineProto) {
            try {
                unset($MadelineProto);
            } catch (\Throwable $e) {
                $this->setErrorMsg('Shutdown error: ' . $e->getMessage());
            }
        }

        gc_collect_cycles();

        return [
            'readed'    => $countMsg,
            'filtered'  => $countMsgFiltered,
            'lastId'    => $this->apiChannel->last_post_id,
            'lastDate'  => $this->apiChannel->last_date_check,
        ];
    }

    private function checkMinLength(&$messages): bool
    {
        if ($this->minLengthPost?->value) {
            if (!count($messages)) {
                return false;
            }

            foreach ($messages as $key => $message) {
                // Может быть в кейсе: [_] => messageService
                if (!isset($message['message'])) {
                    unset($messages[$key]);
                    continue;
                }

                // Игнорируем сообщения от каналов
                if (!isset($message['from_id'])) {
                    unset($messages[$key]);
                    continue;
                }

                if (Str::length($message['message']) < $this->minLengthPost?->value) {
                    $this->setInfoMsg('Сообщение пропущено из-за ограничения мин. длины '.$this->minLengthPost?->value.': '.Str::length($message['message']));
                    unset($messages[$key]);
                }
            }
        }

        return true;
    }

    private function checkReplyTo(&$messages): bool
    {
        if (isset($this->apiChannel->options['reply_to_msg_id'])) {
            if (!count($messages)) {
                return false;
            }

            $replyToMsgIds = explode(',', $this->apiChannel->options['reply_to_msg_id']);
            $replyToMsgIds = array_map('trim', $replyToMsgIds);

            $messagesNew = [];

            foreach ($messages as $key => $message) {
                foreach ($replyToMsgIds as $replyToMsgId) {
                    // Если выбран топик 1 и у сообщения нет ответа, то оно находится в топике 1
                    if ($replyToMsgId == 1 AND !isset($message['reply_to'])) {
                        $messagesNew[$message['id']] = $message;
                        break;
                    }

                    if (
                        isset($message['reply_to']) AND
                        isset($message['reply_to']['reply_to_msg_id']) AND
                        $message['reply_to']['reply_to_msg_id'] == $replyToMsgId
                    ) {
                        $messagesNew[$message['id']] = $message;
                        break;
                    }
                }

                if (!isset($messagesNew[$message['id']])) {
                    //$this->setInfoMsg('Сообщение пропущено из-за проверки на reply_to_msg_id');
                }
            }

            $messages = $messagesNew;
        }

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

    public function downloadUserPhoto($MadelineProto, ApiPostUser $user, array $photos)
    {
        if (!count($photos)) {
            return false;
        }

        $photo = $photos[0];
        $path = Storage::disk('public')->path(self::PHOTO_PATH);
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        // Проверяем было ли ранее загружено это фото по его ID, нейминг фото "5249052310842238504_c_2.jpg", где "5249052310842238504" это id
        if (!empty($user->photo) AND strpos($user->photo, $photo['id']) !== false) {
            return false;
        }

        $file = $MadelineProto->downloadToDir($photo, $path);
        $filename = basename($file);

        return $filename;
    }
}
