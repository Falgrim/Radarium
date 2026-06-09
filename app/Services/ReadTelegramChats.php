<?php

namespace App\Services;

use Amp\CancelledException;
use Amp\Ipc\Sync\ChannelException;
use Amp\SignalException;
use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use danog\MadelineProto\PeerNotInDbException;
use danog\MadelineProto\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use danog\MadelineProto\Logger;

class ReadTelegramChats
{
    private ApiChannel $apiChannel;

    protected $cronCountPosts;
    protected $minLengthPost;

    /** Флаг: последний обработанный канал упал из-за «The endpoint does not exist» (IPC worker мёртв). */
    private bool $lastChannelHadIpcLoss = false;

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
        $this->lastChannelHadIpcLoss = false;
    }

    private function isIpcEndpointLost(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            $message = $t->getMessage();
            if (str_contains($message, 'The endpoint does not exist')
                || str_contains($message, 'Could not connect to DC')) {
                return true;
            }
        }

        return false;
    }

    private function isTelegramDcUnreachable(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if (str_contains($t->getMessage(), 'Could not connect to DC')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Несколько каналов с одним api_id: один экземпляр MadelineProto на сессию, чтобы не открывать IPC заново
     * на каждый канал (гонка «The endpoint does not exist!» с параллельным cron).
     *
     * @param  Collection<int, ApiChannel>  $channels
     * @param  callable(self): void  $afterEachChannel  вывод сообщений команды после каждого канала
     */
    public function readTelegramChannelsWithSharedSession(Collection $channels, callable $afterEachChannel): void
    {
        $this->truncateMadelineLogIfNeeded();

        $invalid = $channels->filter(fn (ApiChannel $ch) => ! $this->channelHasTelegramCredentials($ch));
        $valid = $channels->filter(fn (ApiChannel $ch) => $this->channelHasTelegramCredentials($ch));

        foreach ($invalid as $channel) {
            $this->unsetMessages();
            $this->apiChannel = $channel;
            $this->setWarnMsg('Канал ID'.$channel->id.': нет api_id и/или api_hash');
            $afterEachChannel($this);
        }

        foreach ($valid->groupBy(fn (ApiChannel $ch) => (int) $ch->options['api_id']) as $apiId => $group) {
            $first = $group->first();
            $settings = $this->buildMadelineSettings($first);
            $sessionName = 'session.madeline.' . $apiId;

            $MadelineProto = null;

            try {
                $MadelineProto = new \danog\MadelineProto\API($sessionName, $settings);

                if (!$MadelineProto->getSelf()) {
                    $MadelineProto->start();
                }

                $remaining = $group->values();
                foreach ($remaining as $idx => $channel) {
                    $this->unsetMessages();
                    $this->readChannelWithMadeline($MadelineProto, $channel);
                    $afterEachChannel($this);

                    if ($this->lastChannelHadIpcLoss) {
                        $skipped = $remaining->slice($idx + 1);
                        if ($skipped->isNotEmpty()) {
                            $this->unsetMessages();
                            $ids = $skipped->map(fn (ApiChannel $c) => $c->id)->implode(', ');
                            $msg = 'Сессия '.$sessionName.': IPC недоступен (worker MadelineProto не подключился к Telegram DC). Пропущено каналов: '.$skipped->count().' [ID: '.$ids.']';
                            $this->setErrorMsg($msg);
                            Log::channel('post_parser')->error('ReadTelegramChats abort group on IPC loss', [
                                'session' => $sessionName,
                                'skipped_channel_ids' => $skipped->pluck('id')->all(),
                            ]);
                            $afterEachChannel($this);
                        }
                        break;
                    }
                }
            } finally {
                $this->releaseMadelineClient($MadelineProto);
                $this->finalizeMadelineProcess();
            }
        }
    }

    public function read(ApiChannel $apiChannel): array
    {
        $this->unsetMessages();
        $this->truncateMadelineLogIfNeeded();
        $this->apiChannel = $apiChannel;

        if (!$this->channelHasTelegramCredentials($this->apiChannel)) {
            $this->setWarnMsg('Канал ID'.$this->apiChannel->id.': нет api_id и/или api_hash');

            return [];
        }

        $settings = $this->buildMadelineSettings($this->apiChannel);
        $sessionName = 'session.madeline.' . (int) $this->apiChannel->options['api_id'];

        $MadelineProto = null;

        try {
            $MadelineProto = new \danog\MadelineProto\API($sessionName, $settings);

            if (!$MadelineProto->getSelf()) {
                $MadelineProto->start();
            }

            return $this->readChannelWithMadeline($MadelineProto, $this->apiChannel);
        } finally {
            $this->releaseMadelineClient($MadelineProto);
            $this->finalizeMadelineProcess();
        }
    }

    private function channelHasTelegramCredentials(ApiChannel $channel): bool
    {
        return count($channel->options)
            && isset($channel->options['api_id'], $channel->options['api_hash']);
    }

    private function truncateMadelineLogIfNeeded(): void
    {
        $logPath = storage_path('logs/MadelineProto.log');
        $maxLogSize = 10 * 1024 * 1024;
        if (file_exists($logPath) && filesize($logPath) > $maxLogSize) {
            file_put_contents($logPath, '');
        }
    }

    private function buildMadelineSettings(ApiChannel $channel): Settings
    {
        return MadelineConnectionConfigurator::buildSettings(
            $channel->options['api_id'],
            $channel->options['api_hash'],
            Logger::ULTRA_VERBOSE
        );
    }

    /**
     * Отпустить ссылку на клиент MadelineProto (без API::finalize — его один раз в конце прогона).
     */
    private function releaseMadelineClient(?\danog\MadelineProto\API &$MadelineProto): void
    {
        if ($MadelineProto === null) {
            return;
        }

        $client = $MadelineProto;
        $MadelineProto = null;

        try {
            unset($client);
        } catch (\Throwable $e) {
            Log::channel('post_parser')->warning('ReadTelegramChats MadelineProto unset', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Один раз после завершения работы с сессией: дождаться async-деструкторов (иначе зомби worker).
     */
    private function finalizeMadelineProcess(): void
    {
        try {
            \danog\MadelineProto\API::finalize();
        } catch (\Throwable $e) {
            Log::channel('post_parser')->warning('ReadTelegramChats API::finalize', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isInterruptSignal(\Throwable $e): bool
    {
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if ($t instanceof SignalException) {
                return true;
            }
            if (str_contains($t->getMessage(), 'SIGINT')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{readed: int, filtered: int, lastId: mixed, lastDate: mixed}
     */
    private function readChannelWithMadeline(\danog\MadelineProto\API $MadelineProto, ApiChannel $apiChannel): array
    {
        $this->apiChannel = $apiChannel;

        if (!$this->apiChannel->last_date_check) {
            $offsetDate = $this->apiChannel->post_from_date ? $this->apiChannel->post_from_date : '2025-01-01 00:00:00';
        } else {
            $offsetDate = $this->apiChannel->last_date_check;
        }

        $params = [
            'peer' => $this->apiChannel->link,
            'limit' => $this->cronCountPosts?->value ?? 100,
            'offset_date' => strtotime($offsetDate),
        ];

        $this->setInfoMsg('Выборка с даты: '.date('H:i:s d.m.Y', $params['offset_date']));

        try {
            $messages = $this->getHistoryWithCancelledRetries($MadelineProto, $params);
        } catch (ChannelException $e) {
            $this->setErrorMsg('ChannelException ['.$e::class.']: '.$e->getMessage());

            return [];
        } catch (\Throwable $e) {
            if ($this->isInterruptSignal($e)) {
                $this->setWarnMsg('Прервано (Ctrl+C / SIGINT)');
                Log::channel('post_parser')->notice('ReadTelegramChats getHistory interrupted', [
                    'channel_id' => $this->apiChannel->id,
                ]);

                return [];
            }
            if ($this->isIpcEndpointLost($e)) {
                $this->lastChannelHadIpcLoss = true;
            }
            if ($this->isTelegramDcUnreachable($e)) {
                $this->setWarnMsg(
                    'MadelineProto не достучался до Telegram DC. Проверьте: php artisan config:clear, '
                    .'MPROTO_PROXY_ENABLED и systemctl status xray, зависшие MadelineProto worker.'
                );
            }
            $this->setErrorMsg('getHistory ['.$e::class.']: '.$e->getMessage());
            Log::channel('post_parser')->warning('ReadTelegramChats getHistory', [
                'channel_id' => $this->apiChannel->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $messages = array_reverse($messages['messages']);

        $messagesOrigin = $messages;
        $countMsg = count($messages);

        $this->checkReplyTo($messages);
        $this->checkMinLength($messages);

        $addedCount = 0;
        $countMsgFiltered = 0;

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

                $userInfo = null;
                try {
                    $this->setInfoMsg('Add ID: ' . $message['id']);
                    $userInfo = $MadelineProto->getInfo($message['from_id']);
                } catch (PeerNotInDbException $e) {
                    $this->setErrorMsg($e->getMessage());
                }

                $userData = [];
                if (isset($userInfo['User'])) {
                    $userData = [
                        'first_name' => $userInfo['User']['first_name'] ?? null,
                        'last_name' => $userInfo['User']['last_name'] ?? null,
                        'username' => $userInfo['User']['username'] ?? '',
                        'user_id' => $userInfo['user_id'],
                        'user_type' => $userInfo['type'],
                        'phone' => $userInfo['User']['phone'] ?? null,
                        'last_online' => isset($userInfo['User']['status']['was_online']) ? (new \DateTime())->setTimestamp($userInfo['User']['status']['was_online'])->format('Y-m-d H:i:s') : null,
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

                        $this->setInfoMsg('Обновлен пользователь: '.$user->id);
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
                            $this->setWarnMsg('Не удалось скачать фото профиля ID'.$user->id.': '.$e->getMessage());
                        }
                    }
                }

                $post = ApiChannelPost::updateOrCreate([
                    'api_channel_id' => $this->apiChannel->id,
                    'post_id' => $message['id'],
                ], [
                    'api_post_user_id' => $user?->id ?? 0,
                    'api_channel_id' => $this->apiChannel->id,
                    'user_login' => $userData['username'] ?? '',
                    'user_login_id' => $message['from_id'],
                    'post_id' => $message['id'],
                    'post_date' => Carbon::createFromTimestamp($message['date'])->toDateTimeString(),
                    'post' => trim($message['message']),
                    'ai_parse_status' => is_null($postCheck) ? ApiChannelPostStatusEnum::InQueue : ApiChannelPostStatusEnum::Duplicate,
                ]);

                if ($post->wasRecentlyCreated === true) {
                    $addedCount++;
                    $this->setInfoMsg('Создан новый пост: '.$post->id);
                } else {
                    $this->setInfoMsg('Обновлен пост: '.$post->id);
                }
            }
        }

        if (count($messagesOrigin)) {
            $lastPostId = last($messagesOrigin)['id'] ?? null;
            $lastDate = last($messagesOrigin)['date'] ?? null;

            if (!is_null($lastPostId)) {
                if (!$this->apiChannel->last_post_id OR $this->apiChannel->last_post_id < $lastPostId) {
                    $this->apiChannel->last_post_id = $lastPostId;
                }

                $lastDate = (new \DateTime())->setTimestamp($lastDate)->format('Y-m-d H:i:s');
                if (!$this->apiChannel->last_date_check) {
                    $this->apiChannel->last_date_check = $lastDate;
                } elseif ($this->apiChannel->last_date_check->lessThan($lastDate)) {
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
        } elseif (!$this->apiChannel->last_date_check || !$this->apiChannel->last_post_id) {
            $this->apiChannel->last_date_check = Carbon::parse($offsetDate)->addDays(3);
            $this->apiChannel->save();
        }

        Log::channel('post_parser')->info($apiChannel->id.': Всего постов '.$countMsg.'; Отфильтровано '.$countMsgFiltered.'; Новых '.$addedCount);
        $this->setInfoMsg('Прочитано '.$countMsg.'; Допущенных: '.$countMsgFiltered);
        $this->setInfoMsg('Последний ID: '.$this->apiChannel->last_post_id);
        $this->setInfoMsg('Последняя дата: '.$this->apiChannel->last_date_check);

        return [
            'readed' => $countMsg,
            'filtered' => $countMsgFiltered,
            'lastId' => $this->apiChannel->last_post_id,
            'lastDate' => $this->apiChannel->last_date_check,
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

    /**
     * messages.getHistory часто бросает Amp\CancelledException при кратком обрыве связи с DC или таймауте.
     *
     * @return array<string, mixed> ответ MTProto (в т.ч. ключ 'messages')
     */
    private function getHistoryWithCancelledRetries(\danog\MadelineProto\API $api, array $params): array
    {
        $maxAttempts = max(1, min(5, (int) config('services.madeline_proto.get_history_max_attempts', 3)));
        $last = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $api->messages->getHistory($params);
            } catch (CancelledException $e) {
                $last = $e;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                Log::channel('post_parser')->notice('ReadTelegramChats getHistory CancelledException, retry', [
                    'channel_id' => $this->apiChannel->id,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);
                sleep(min(2 * $attempt, 8));
            }
        }

        throw $last ?? new \RuntimeException('getHistory: no attempts executed');
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
