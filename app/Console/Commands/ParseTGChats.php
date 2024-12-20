<?php

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ParseTGChats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tg_parse';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Скрипт парсинга чатов ТГ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $channels = ApiChannel::where('channel_source', ApiChannelSourceEnum::Telegram)
            ->where('status', ApiChannelStatusEnum::Active)
            ->get();

        if (!count($channels)) {
            $this->warn('Нет списка каналов для парсинга');
            return 1;
        }

        $cronCountPosts = Repositories::setting()->findByName('cron_count_posts');
        $minLengthPost = Repositories::setting()->findByName('min_length_post');

        foreach ($channels as $channel) {
            if (!count($channel->options) OR !isset($channel->options['api_id']) OR !isset($channel->options['api_hash'])) {
                $this->warn('Канал ID'.$channel->id.': нет api_id и/или api_hash');
                continue;
            }

            $settings = (new \danog\MadelineProto\Settings\AppInfo)
                ->setApiId($channel->options['api_id'])
                ->setApiHash($channel->options['api_hash']);

            $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);

            $settings = (new \danog\MadelineProto\Settings\Logger)
                ->setLevel(\danog\MadelineProto\Logger::LEVEL_ERROR);
            $MadelineProto->updateSettings($settings);

            $MadelineProto->start();

            $params = [
                'peer'          => $channel->link,
                'offset_id'     => 0,
                'offset_date'   => strtotime('-30 days'),
                'add_offset'    => 0,
                'limit'         => $cronCountPosts?->value ?? 50,
                'max_id'        => 0,
                'min_id'        => $channel->last_post_id ?? 0,
                'hash'          => 0,
            ];

            $messages = $MadelineProto->messages->getHistory($params);

            /* Сообщения, сортировка по дате (новые сверху) */
            $messages = array_reverse($messages['messages']);

            $countMsg = count($messages);
            if (count($messages)) {
                if (isset($channel->options['reply_to_msg_id'])) {
                    foreach ($messages as $key => $message) {
                        if (
                            !isset($message['reply_to']) or
                            !isset($message['reply_to']['reply_to_msg_id']) or
                            $message['reply_to']['reply_to_msg_id'] != $channel->options['reply_to_msg_id']
                        ) {
                            $this->info('Сообщение пропущено из-за проверки на reply_to_msg_id');
                            unset($messages[$key]);
                        }
                    }
                }
            }

            if ($minLengthPost?->value) {
                foreach ($messages as $key => $message) {
                    if (Str::length($message['message']) < $minLengthPost?->value) {
                        $this->info('Сообщение пропущено из-за ограничения мин. длины '.$minLengthPost?->value.': '.Str::length($message['message']));
                        unset($messages[$key]);
                    }
                }
            }

            //$MadelineProto->report('1111');

            if ($countMsgFiltered = count($messages)) {
                $lastPostId = last($messages)['id'] ?? null;
                foreach ($messages as $message) {
                    $this->info('Add ID: '.$message['id']);

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
                    }

                    if (count($userData)) {
                        $user = ApiPostUser::updateOrCreate([
                            'user_id' => $message['from_id'],
                            'channel_source' => $channel->channel_source,
                        ], [
                            'user_id' => $message['from_id'],
                            'channel_source' => $channel->channel_source,
                            'first_name'    => $userData['first_name'],
                            'username'      => $userData['username'],
                            'user_type'     => $userData['user_type'],
                            'phone'         => $userData['phone'],
                            'last_online_date' => $userData['last_online'],
                        ]);

                        $this->info('Создан новый пользователь: '.$user->id);
                    }

                    $post = ApiChannelPost::updateOrCreate([
                        'api_channel_id' => $channel->id,
                        'post_id' => $message['id']
                    ], [
                        'api_post_user_id'  => $user?->id ?? 0,
                        'api_channel_id'    => $channel->id,
                        'user_login'        => $userData['username'] ?? '',
                        'user_login_id'     => $message['from_id'],
                        'post_id'           => $message['id'],
                        'post_date'         => (new \DateTime())->setTimestamp($message['date'])->format("Y-m-d H:i:s"),
                        'post'              => trim($message['message']),
                        'ai_parse_status'   => ApiChannelPostStatusEnum::InQueue,
                    ]);

                    $this->info('Создан новый пост: '.$post->id);
                }

                if (!is_null($lastPostId)) {
                    $channel->last_post_id = $lastPostId;
                    $channel->save();
                }
            }

            Log::channel('crm_service')->info('Прочитано '.$countMsg.'; Отфильтрованных: '.$countMsgFiltered);
            $this->info('Прочитано '.$countMsg.'; Отфильтрованных: '.$countMsgFiltered);
        }

        $this->info('Завершено');
    }
}
