<?php

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Models\User;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiTubus
{
    protected ?string $apiToken;

    protected string $url = 'https://tubus.pro/api';

    public function __construct()
    {
        $this->apiToken = config('services.tubus.token');
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=UTF-8',
        ];
    }

    public function checkUser(string $phone): array
    {
        $phone = str_replace('+', '', $phone);
        $data = [
            'phone' => $phone,
        ];

        $result = $this->sendRequest('/user.php', $data);

        return $result;
    }

    public function linkUser(User $user): bool
    {
        $data = [
            'phone' => str_replace('+', '', $user->phone),
            'performer_user_id' => $user->id,
        ];

        $result = $this->sendRequest('/user_performer_user_id.php', $data);

        return $result['status'] === 'ok';
    }

    protected function sendRequest(string $method, array $data)
    {
        if (is_null($this->apiToken)) {
            throw new \Exception('Не указан токен для запроса');
        }

        $data['key'] = $this->apiToken;

        $headers = $this->getHeaders();

        $this->logging($data);
        $response = Http::withHeaders($headers)->asForm()->post($this->url.$method, $data);

        if ($response->status() === 401) {
            $this->logging($response->body(), true);
            throw new \Exception('Ошибка доступа: '.$response->body());
        }

        if ($response->status() !== 200) {
            $this->logging($response->body(), true);
            throw new \Exception('Не удалось отправить запрос: '.$response->body());
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
