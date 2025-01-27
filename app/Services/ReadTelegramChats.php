<?php

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        if (!count($this->apiChannel->options) OR !isset($this->apiChannel->options['api_id']) OR !isset($this->apiChannel->options['api_hash'])) {
            $this->setWarnMsg('Канал ID'.$this->apiChannel->id.': нет api_id и/или api_hash');
            return [];
        }

        $settings = (new \danog\MadelineProto\Settings\AppInfo)
            ->setApiId($this->apiChannel->options['api_id'])
            ->setApiHash($this->apiChannel->options['api_hash']);

        $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
        $settings = (new \danog\MadelineProto\Settings\Logger)->setLevel(\danog\MadelineProto\Logger::LEVEL_ERROR);
        $MadelineProto->updateSettings($settings);

        $MadelineProto->start();

        $params = [
            'peer'          => $this->apiChannel->link,
            'limit'         => $this->cronCountPosts?->value ?? 100,
            'offset_date'   => !$this->apiChannel->last_date_check ? strtotime('-30 days') : strtotime($this->apiChannel->last_date_check),
        ];

        $messages = $MadelineProto->messages->getHistory($params);

        /* Сообщения, сортировка по дате (новые сверху) */
        $messages = array_reverse($messages['messages']);
        $countMsg = count($messages);

        if (count($messages)) {
            if (isset($this->apiChannel->options['reply_to_msg_id'])) {
                foreach ($messages as $key => $message) {
                    if (
                        !isset($message['reply_to']) or
                        !isset($message['reply_to']['reply_to_msg_id']) or
                        $message['reply_to']['reply_to_msg_id'] != $this->apiChannel->options['reply_to_msg_id']
                    ) {
                        //$this->setInfoMsg('Сообщение пропущено из-за проверки на reply_to_msg_id');
                        unset($messages[$key]);
                    } else {
                        //$this->setInfoMsg($message['id'].': '.(new \DateTime())->setTimestamp($message['date'])->format("Y-m-d H:i:s"));
                    }
                }
            }
        }

        if ($this->minLengthPost?->value) {
            foreach ($messages as $key => $message) {
                if (Str::length($message['message']) < $this->minLengthPost?->value) {
                    $this->setInfoMsg('Сообщение пропущено из-за ограничения мин. длины '.$this->minLengthPost?->value.': '.Str::length($message['message']));
                    unset($messages[$key]);
                }
            }
        }

        if ($countMsgFiltered = count($messages)) {
            $lastPostId = last($messages)['id'] ?? null;
            $lastDate = last($messages)['date'] ?? null;

            foreach ($messages as $message) {
                if ($this->apiChannel->last_post_id AND $this->apiChannel->last_post_id >= $message['id']) {
                    continue;
                }

                $postCheck = ApiChannelPost::where('post_id', '!=', $message['id'])
                    ->where('post', trim($message['message']))
                    ->orderByDesc('post_date')
                    ->first();

                if (!is_null($postCheck)) {
                    $this->setWarnMsg('Дубликат: '.$message['id'].' (БД '.$postCheck->id.')');
                }

                $this->setInfoMsg('Add ID: '.$message['id']);

                $userInfo = $MadelineProto->getInfo($message['from_id']);

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
                    $user = ApiPostUser::updateOrCreate([
                        'user_id' => $message['from_id'],
                        'channel_source' => $this->apiChannel->channel_source,
                    ], [
                        'user_id' => $message['from_id'],
                        'channel_source' => $this->apiChannel->channel_source,
                        'first_name'    => $userData['first_name'],
                        'username'      => $userData['username'],
                        'user_type'     => $userData['user_type'],
                        'phone'         => $userData['phone'],
                        'last_online_date' => $userData['last_online'],
                    ]);

                    if ($user->wasRecentlyCreated === true) {
                        $this->setInfoMsg('Создан новый пользователь: '.$user->id);
                    } else {
                        $this->setInfoMsg('Обновлен пользователь: ' . $user->id);
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
                    $this->setInfoMsg('Создан новый пост: ' . $post->id);
                } else {
                    $this->setInfoMsg('Обновлен пост: ' . $post->id);
                }
            }

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
        }

        Log::channel('crm_service')->info('Прочитано '.$countMsg.'; Отфильтрованных: '.$countMsgFiltered);
        $this->setInfoMsg('Прочитано '.$countMsg.'; Отфильтрованных: '.$countMsgFiltered);
        $this->setInfoMsg('Последний ID: '.$this->apiChannel->last_post_id);
        $this->setInfoMsg('Последняя дата: '.$this->apiChannel->last_date_check);

        return [
            'readed'    => $countMsg,
            'filtered'  => $countMsgFiltered,
            'lastId'    => $this->apiChannel->last_post_id,
            'lastDate'  => $this->apiChannel->last_date_check,
        ];
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
