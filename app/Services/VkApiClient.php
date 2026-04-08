<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VkApiClient
{
    private float $lastCallAt = 0;

    /**
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = []): array
    {
        $token = config('vk.service_token');
        if ($token === null || $token === '') {
            throw new RuntimeException('VK_SERVICE_TOKEN не задан');
        }

        $this->throttle();

        $query = array_merge($params, [
            'access_token' => $token,
            'v' => config('vk.api_version'),
        ]);

        $response = Http::timeout((int) config('vk.http_timeout'))
            ->get('https://api.vk.com/method/'.$method, $query);

        if (! $response->successful()) {
            throw new RuntimeException('VK HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('VK: некорректный JSON');
        }

        if (isset($json['error'])) {
            $code = $json['error']['error_code'] ?? 0;
            $msg = $json['error']['error_msg'] ?? 'unknown';

            throw new RuntimeException("VK API error {$code}: {$msg}");
        }

        return $json['response'] ?? [];
    }

    private function throttle(): void
    {
        $minUs = (int) config('vk.min_interval_us', 350_000);
        if ($minUs <= 0) {
            return;
        }

        $now = microtime(true);
        $elapsed = $now - $this->lastCallAt;
        $need = ($minUs / 1_000_000) - $elapsed;
        if ($need > 0) {
            usleep((int) ($need * 1_000_000));
        }
        $this->lastCallAt = microtime(true);
    }

    /**
     * owner_id для wall.get (отрицательный для сообществ).
     */
    public function resolveGroupOwnerId(string $screenName): int
    {
        $screenName = trim($screenName);
        if ($screenName === '') {
            throw new RuntimeException('Пустой screen_name VK');
        }

        $res = $this->call('utils.resolveScreenName', ['screen_name' => $screenName]);
        $type = $res['type'] ?? '';
        $objectId = (int) ($res['object_id'] ?? 0);

        if ($objectId === 0) {
            throw new RuntimeException('VK: не удалось определить сообщество для '.$screenName);
        }

        if (! in_array($type, ['group', 'page', 'event'], true)) {
            throw new RuntimeException('VK: '.$screenName.' имеет тип '.$type.', ожидалось сообщество');
        }

        return -$objectId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function wallGet(int $ownerId, int $offset, int $count): array
    {
        $count = min(max($count, 1), 100);

        $res = $this->call('wall.get', [
            'owner_id' => $ownerId,
            'offset' => $offset,
            'count' => $count,
        ]);

        if (isset($res['items']) && is_array($res['items'])) {
            return $res['items'];
        }

        return is_array($res) ? $res : [];
    }

    /**
     * @param  array<int>  $userIds  положительные id пользователей
     * @return array<int, array<string, mixed>> id => поля профиля
     */
    public function usersGetBatched(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, fn ($id) => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($userIds, 900) as $chunk) {
            $list = $this->call('users.get', [
                'user_ids' => implode(',', $chunk),
                'fields' => 'screen_name,photo_200,photo_100',
            ]);
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (isset($row['id'])) {
                    $out[(int) $row['id']] = $row;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<int>  $groupIds  положительные id групп
     * @return array<int, array<string, mixed>> id => поля
     */
    public function groupsGetByIdBatched(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter($groupIds, fn ($id) => $id > 0)));
        if ($groupIds === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($groupIds, 500) as $chunk) {
            $list = $this->call('groups.getById', [
                'group_id' => implode(',', $chunk),
                'fields' => 'screen_name,photo_200,photo_100',
            ]);
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $row) {
                if (isset($row['id'])) {
                    $out[(int) $row['id']] = $row;
                }
            }
        }

        return $out;
    }

    public static function screenNameFromLink(string $link): ?string
    {
        $path = parse_url($link, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }

        return $path;
    }
}
