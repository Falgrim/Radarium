<?php

namespace App\Services;

use App\Enum\IsCompanyEnum;
use App\Models\User;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiTubus
{
    protected string $apiToken;

    protected string $url = 'https://tubus.pro/api';

    protected bool $apiToken = null;

    public function __construct()
    {
        $this->apiToken = config('services.tubus.token');
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    public function checkUser(string $phone): array
    {
        $data = [
            'key' => $this->apiToken,
            'phone' => $phone,
        ];

        $result = $this->sendRequest('/user_test.php', $data);

        return $result['user'];
    }

    public function addUser(User $user): bool
    {
        $data = [
            'key' => $this->apiToken,
            'phone' => $user->phone,
            'performer_user_id' => $user->id,
        ];

        $result = $this->sendRequest('/user_performer_user_id_test.php', $data);

        return $result['status'] === 'ok';
    }

    protected function sendRequest(string $method, array $data)
    {
        $headers = $this->getHeaders();
        $json = json_encode($data);

        $this->logging($json);
        $response = Http::withHeaders($headers)->post($this->url.$method, $json);

        if ($response->status() !== 200) {
            throw new \Exception('Не удалось отправить запрос: '.$response->body());
        }

        if ($response->status() !== 401) {
            $this->logging($response->body(), true);
            throw new \Exception('Ошибка доступа: '.$response->body());
        }

        if (!$response->json()['phone']) {
            $this->logging($response->body(), true);
            throw new \Exception('Не удалось получить ответ: '.$response->body());
        }

        $this->logging($response->json());

        return $response->json();
    }

    public function logging(mixed $text, bool $isError = false)
    {
        if ($isError === true) {
            Log::channel('tubus')->error($text);
        } else {
            Log::channel('tubus')->info($text);
        }
    }
}
