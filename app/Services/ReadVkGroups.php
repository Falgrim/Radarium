<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostUserMailingStatusEnum;
use App\Infrastructures\Facades\Repositories;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ApiPostUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReadVkGroups
{
    private ApiChannel $apiChannel;

    protected $cronCountPosts;

    protected $minLengthPost;

    protected array $messages = [
        'info' => [],
        'warn' => [],
        'error' => [],
    ];

    public function __construct(
        protected VkApiClient $vkApi,
    ) {
        $this->cronCountPosts = Repositories::setting()->findByName('cron_count_posts');
        $this->minLengthPost = Repositories::setting()->findByName('min_length_post');
    }

    private function unsetMessages(): void
    {
        $this->messages = [
            'info' => [],
            'warn' => [],
            'error' => [],
        ];
    }

    /**
     * @return array{readed: int, filtered: int, lastId: int|null, lastDate: Carbon|null}
     */
    public function read(ApiChannel $apiChannel): array
    {
        $this->unsetMessages();
        $this->apiChannel = $apiChannel;

        if (config('vk.service_token') === null || config('vk.service_token') === '') {
            $this->setWarnMsg('Канал ID'.$this->apiChannel->id.': VK_SERVICE_TOKEN не задан');

            return [
                'readed' => 0,
                'filtered' => 0,
                'lastId' => $this->apiChannel->last_post_id,
                'lastDate' => $this->apiChannel->last_date_check,
            ];
        }

        try {
            $ownerId = $this->resolveOwnerId();
        } catch (Throwable $e) {
            $this->setErrorMsg('owner_id: '.$e->getMessage());

            return [
                'readed' => 0,
                'filtered' => 0,
                'lastId' => $this->apiChannel->last_post_id,
                'lastDate' => $this->apiChannel->last_date_check,
            ];
        }

        $targetCount = (int) ($this->cronCountPosts?->value ?? 100);
        $targetCount = max(1, min($targetCount, 500));

        $postFromTs = $this->postFromTimestamp();
        $maxPages = (int) config('vk.max_wall_pages_per_run', 15);

        $allFetchedOrderedNewestFirst = [];
        $offset = 0;
        $pages = 0;

        try {
            while ($pages < $maxPages && count($allFetchedOrderedNewestFirst) < $targetCount) {
                $pageSize = min(100, $targetCount - count($allFetchedOrderedNewestFirst));
                if ($pageSize < 1) {
                    break;
                }

                $chunk = $this->vkApi->wallGet($ownerId, $offset, $pageSize);
                $pages++;

                if ($chunk === []) {
                    break;
                }

                $stopPagination = false;

                foreach ($chunk as $post) {
                    if (! is_array($post)) {
                        continue;
                    }

                    $postId = (int) ($post['id'] ?? 0);
                    $postDate = (int) ($post['date'] ?? 0);

                    if ($postDate > 0 && $postDate < $postFromTs) {
                        $stopPagination = true;
                        break;
                    }

                    if ($this->apiChannel->last_post_id && $postId > 0 && $postId <= $this->apiChannel->last_post_id) {
                        $stopPagination = true;
                        break;
                    }

                    $allFetchedOrderedNewestFirst[] = $post;
                }

                if ($stopPagination) {
                    break;
                }

                if (count($chunk) < $pageSize) {
                    break;
                }

                $offset += count($chunk);
            }
        } catch (Throwable $e) {
            $this->setErrorMsg('wall.get: '.$e->getMessage());

            return [
                'readed' => 0,
                'filtered' => 0,
                'lastId' => $this->apiChannel->last_post_id,
                'lastDate' => $this->apiChannel->last_date_check,
            ];
        }

        $countMsg = count($allFetchedOrderedNewestFirst);
        $this->setInfoMsg('Получено записей со стены: '.$countMsg);

        $messagesOrigin = array_reverse($allFetchedOrderedNewestFirst);

        $toProcess = $this->filterPosts($allFetchedOrderedNewestFirst);
        $countMsgFiltered = count($toProcess);

        $profiles = $this->loadProfiles($toProcess);

        $addedCount = 0;

        foreach ($toProcess as $post) {
            $messageId = (int) ($post['id'] ?? 0);
            if ($messageId === 0) {
                continue;
            }

            if ($this->apiChannel->last_post_id && $this->apiChannel->last_post_id >= $messageId) {
                continue;
            }

            $text = $this->buildWallPostText($post);

            $postCheck = ApiChannelPost::where('post_id', '!=', $messageId)
                ->where('post', $text)
                ->where('api_channel_id', $this->apiChannel->id)
                ->orderByDesc('post_date')
                ->first();

            if (! is_null($postCheck)) {
                $this->setWarnMsg('Дубликат: '.$messageId.' (БД '.$postCheck->id.')');
            }

            $authorVkId = $this->authorId($post);
            $user = null;
            $userData = $profiles[$authorVkId] ?? null;

            if ($authorVkId !== 0 && $userData !== null) {
                $user = $this->syncApiPostUser($authorVkId, $userData);
            } elseif ($authorVkId !== 0) {
                $this->setWarnMsg('Нет профиля VK для from_id '.$authorVkId);
            }

            $postRow = ApiChannelPost::updateOrCreate(
                [
                    'api_channel_id' => $this->apiChannel->id,
                    'post_id' => $messageId,
                ],
                [
                    'api_post_user_id' => $user?->id ?? 0,
                    'api_channel_id' => $this->apiChannel->id,
                    'user_login' => $userData['username'] ?? '',
                    'user_login_id' => $authorVkId,
                    'post_id' => $messageId,
                    'post_date' => Carbon::createFromTimestamp((int) ($post['date'] ?? time()))->toDateTimeString(),
                    'post' => $text,
                    'ai_parse_status' => is_null($postCheck) ? ApiChannelPostStatusEnum::InQueue : ApiChannelPostStatusEnum::Duplicate,
                ]
            );

            if ($postRow->wasRecentlyCreated === true) {
                $addedCount++;
                $this->setInfoMsg('Создан новый пост: '.$postRow->id);
            } else {
                $this->setInfoMsg('Обновлен пост: '.$postRow->id);
            }
        }

        if (count($messagesOrigin)) {
            $lastItem = $messagesOrigin[array_key_last($messagesOrigin)];
            $lastPostId = (int) ($lastItem['id'] ?? 0);
            $lastDate = (int) ($lastItem['date'] ?? 0);

            if ($lastPostId > 0) {
                if (! $this->apiChannel->last_post_id || $this->apiChannel->last_post_id < $lastPostId) {
                    $this->apiChannel->last_post_id = $lastPostId;
                }

                $lastDateStr = (new \DateTime)->setTimestamp($lastDate)->format('Y-m-d H:i:s');
                $lastDateCarbon = Carbon::parse($lastDateStr);
                if (! $this->apiChannel->last_date_check) {
                    $this->apiChannel->last_date_check = $lastDateStr;
                } elseif ($this->apiChannel->last_date_check->lessThan($lastDateCarbon)) {
                    $this->apiChannel->last_date_check = $lastDateStr;
                } else {
                    $lastDateCarbon = $this->apiChannel->last_date_check->copy()->addDays(3);
                    if (! $lastDateCarbon->greaterThan(Carbon::now())) {
                        $this->apiChannel->last_date_check = $this->apiChannel->last_date_check->copy()->addDays(3);
                    } else {
                        $this->apiChannel->last_date_check = Carbon::now();
                    }
                }

                if ($this->apiChannel->isDirty()) {
                    $this->apiChannel->save();
                }
            }
        } elseif (! $this->apiChannel->last_date_check || ! $this->apiChannel->last_post_id) {
            $this->apiChannel->last_date_check = Carbon::parse($this->offsetDateString())->addDays(3);
            $this->apiChannel->save();
        }

        Log::channel('post_parser')->info($apiChannel->id.': VK — всего '.$countMsg.'; допущено '.$countMsgFiltered.'; новых '.$addedCount);
        $this->setInfoMsg('Прочитано '.$countMsg.'; Допущенных: '.$countMsgFiltered);
        $this->setInfoMsg('Последний ID: '.$this->apiChannel->last_post_id);
        $this->setInfoMsg('Последняя дата: '.($this->apiChannel->last_date_check?->format('Y-m-d H:i:s') ?? ''));

        return [
            'readed' => $countMsg,
            'filtered' => $countMsgFiltered,
            'lastId' => $this->apiChannel->last_post_id,
            'lastDate' => $this->apiChannel->last_date_check,
        ];
    }

    private function offsetDateString(): string
    {
        if (! $this->apiChannel->last_date_check) {
            return $this->apiChannel->post_from_date
                ? $this->apiChannel->post_from_date->format('Y-m-d').' 00:00:00'
                : '2025-01-01 00:00:00';
        }

        return $this->apiChannel->last_date_check->format('Y-m-d H:i:s');
    }

    private function postFromTimestamp(): int
    {
        $raw = $this->offsetDateString();

        return Carbon::parse($raw)->getTimestamp();
    }

    /**
     * @param  array<int, array<string, mixed>>  $postsNewestFirst
     * @return list<array<string, mixed>>
     */
    private function filterPosts(array $postsNewestFirst): array
    {
        $minLen = (int) ($this->minLengthPost?->value ?? 0);
        $skipReposts = (bool) config('vk.skip_reposts', true);
        $out = [];

        foreach ($postsNewestFirst as $post) {
            if (! is_array($post)) {
                continue;
            }

            if ($skipReposts && ! empty($post['copy_history'])) {
                $this->setInfoMsg('Пропуск репоста id '.($post['id'] ?? '?'));

                continue;
            }

            $text = $this->buildWallPostText($post);
            if ($minLen > 0 && Str::length($text) < $minLen) {
                $this->setInfoMsg('Пропуск по min_length: id '.($post['id'] ?? '?'));

                continue;
            }

            $author = $this->authorId($post);
            if ($author === 0) {
                $this->setInfoMsg('Пропуск: нет автора id '.($post['id'] ?? '?'));

                continue;
            }

            $out[] = $post;
        }

        return $out;
    }

    /**
     * Текст для ИИ: подпись к репосту + цепочка copy_history (рекурсивно).
     *
     * @param  array<string, mixed>  $post
     */
    private function buildWallPostText(array $post): string
    {
        $parts = [];
        $top = trim((string) ($post['text'] ?? ''));
        if ($top !== '') {
            $parts[] = $top;
        }

        if (! empty($post['copy_history']) && is_array($post['copy_history'])) {
            foreach ($post['copy_history'] as $inner) {
                if (! is_array($inner)) {
                    continue;
                }
                $innerText = $this->buildWallPostText($inner);
                if ($innerText !== '') {
                    $parts[] = $innerText;
                }
            }
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<int, array{first_name: string|null, last_name: string|null, username: string, user_type: string, photo_url: string|null}>
     */
    private function loadProfiles(array $posts): array
    {
        $userIds = [];
        $groupIds = [];

        foreach ($posts as $post) {
            $id = $this->authorId($post);
            if ($id > 0) {
                $userIds[] = $id;
            } elseif ($id < 0) {
                $groupIds[] = abs($id);
            }
        }

        $profiles = [];

        try {
            $users = $this->vkApi->usersGetBatched($userIds);
            foreach ($users as $id => $row) {
                $photoUrl = $row['photo_200'] ?? $row['photo_100'] ?? null;
                $profiles[(int) $id] = [
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'username' => (string) ($row['screen_name'] ?? ''),
                    'user_type' => 'user',
                    'photo_url' => is_string($photoUrl) && str_starts_with($photoUrl, 'http') ? $photoUrl : null,
                ];
            }

            $groups = $this->vkApi->groupsGetByIdBatched($groupIds);
            foreach ($groups as $id => $row) {
                $photoUrl = $row['photo_200'] ?? $row['photo_100'] ?? null;
                $profiles[-(int) $id] = [
                    'first_name' => $row['name'] ?? null,
                    'last_name' => null,
                    'username' => (string) ($row['screen_name'] ?? ''),
                    'user_type' => 'group',
                    'photo_url' => is_string($photoUrl) && str_starts_with($photoUrl, 'http') ? $photoUrl : null,
                ];
            }
        } catch (Throwable $e) {
            $this->setErrorMsg('Профили VK: '.$e->getMessage());
        }

        return $profiles;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function authorId(array $post): int
    {
        if (! empty($post['signer_id'])) {
            return (int) $post['signer_id'];
        }

        return (int) ($post['from_id'] ?? 0);
    }

    /**
     * @param  array{first_name: ?string, last_name: ?string, username: string, user_type: string, photo_url: ?string}  $userData
     */
    private function syncApiPostUser(int $authorVkId, array $userData): ApiPostUser
    {
        $user = ApiPostUser::where('user_id', $authorVkId)
            ->where('channel_source', $this->apiChannel->channel_source)
            ->first();

        if (! $user) {
            $user = ApiPostUser::create([
                'user_id' => $authorVkId,
                'channel_source' => $this->apiChannel->channel_source,
                'send_welcome_msg' => ApiPostUserMailingStatusEnum::Waiting,
                'first_name' => $userData['first_name'],
                'username' => $userData['username'],
                'user_type' => $userData['user_type'],
                'phone' => null,
                'last_online_date' => null,
            ]);

            $this->setInfoMsg('Создан новый пользователь VK: '.$user->id);
        } else {
            $user->first_name = $userData['first_name'];
            $user->username = $userData['username'];
            $user->user_type = $userData['user_type'];
            $user->save();

            $this->setInfoMsg('Обновлён пользователь VK: '.$user->id);
        }

        $this->tryDownloadVkAvatar($user, $userData['photo_url'] ?? null);

        return $user;
    }

    private function tryDownloadVkAvatar(ApiPostUser $user, ?string $photoUrl): void
    {
        if (! config('vk.download_avatars') || empty($photoUrl) || ! str_starts_with($photoUrl, 'http')) {
            return;
        }

        try {
            $response = Http::timeout(25)->get($photoUrl);
            if (! $response->successful()) {
                $this->setWarnMsg('VK фото HTTP '.$response->status().' для user_id '.$user->user_id);

                return;
            }

            $body = $response->body();
            if (strlen($body) < 80) {
                return;
            }

            $path = ReadTelegramChats::PHOTO_PATH;
            $disk = Storage::disk('public');
            $fullDir = $disk->path($path);
            if (! is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $filename = 'vk_'.$user->user_id.'.jpg';
            $disk->put($path.'/'.$filename, $body);
            ApiPostUser::where('id', $user->id)->update(['photo' => $filename]);
            $user->photo = $filename;

            $this->setInfoMsg('Сохранено фото VK: '.$filename);
        } catch (Throwable $e) {
            $this->setWarnMsg('VK фото user_id '.$user->user_id.': '.$e->getMessage());
        }
    }

    private function resolveOwnerId(): int
    {
        $opts = $this->apiChannel->options ?? [];

        if (isset($opts['owner_id'])) {
            $id = (int) $opts['owner_id'];
            if ($id < 0) {
                return $id;
            }
            if ($id > 0) {
                return -$id;
            }
        }

        $screen = $opts['screen_name'] ?? null;
        if (! is_string($screen) || trim($screen) === '') {
            $screen = VkApiClient::screenNameFromLink($this->apiChannel->link);
        }

        if ($screen === null || $screen === '') {
            throw new \InvalidArgumentException('Укажите ссылку vk.com или options.screen_name / options.owner_id');
        }

        $ownerId = $this->vkApi->resolveGroupOwnerId($screen);

        $opts['owner_id'] = $ownerId;
        $opts['screen_name'] = $screen;
        $this->apiChannel->options = $opts;
        $this->apiChannel->saveQuietly();

        $this->setInfoMsg('Сохранён owner_id '.$ownerId.' для канала '.$this->apiChannel->id);

        return $ownerId;
    }

    private function setInfoMsg(string $text): void
    {
        $this->messages['info'][] = $text;
    }

    public function getInfoMsg(): array
    {
        return $this->messages['info'];
    }

    private function setWarnMsg(string $text): void
    {
        $this->messages['warn'][] = $text;
    }

    public function getWarnMsg(): array
    {
        return $this->messages['warn'];
    }

    private function setErrorMsg(string $text): void
    {
        $this->messages['error'][] = $text;
    }

    public function getErrorMsg(): array
    {
        return $this->messages['error'];
    }
}
