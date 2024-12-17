<?php

namespace App\Services;

use App\DTO\MedBotCreateClientDTO;
use App\DTO\MedBotDeleteClientDTO;
use App\Exceptions\MedBotException;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;

// https://yandex.cloud/ru/docs/foundation-models/quickstart/yandexgpt#api_2
// https://yandex.cloud/ru/docs/iam/operations/api-key/create#console_1

/*
 * 1) Создать сервисный аккаунт
 * 2) Создать для него API ключ с правами ai.languageModels.user
 * 3) Задать параметры API в конфиге канала API_KEY_TOKEN
 * 4) Создать папку или выбрать из ссылки на страницу Yandex Foundation Models
 * 5) Указать название папки Folder_id
 */

class ApiAIYandex
{
    protected string $apiToken;

    protected string $folderId;

    protected string $promt;

    protected string $text;

    protected string $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

    public function __construct()
    {
    }

    public function setConfig(array $config)
    {
        if (!isset($config['API_KEY_TOKEN'])) {
            throw new Exception('Не указан API_KEY_TOKEN параметр');
        }

        if (!isset($config['Folder_id'])) {
            throw new Exception('Не указан Folder_id параметр');
        }

        $this->apiToken = $config['API_KEY_TOKEN'];
        $this->folderId = $config['Folder_id'];
    }

    public function setPromt(string $promt)
    {
        $this->promt = $promt;
    }

    public function setText(string $text)
    {
        $this->text = $text;
    }

    protected function getHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Api-Key '.$this->apiToken,
        ];
    }

    protected function generateJson()
    {
        $json = [
            'modelUri' => 'gpt://'.$this->folderId.'/yandexgpt/rc',
            'completionOptions' => [
                'stream' => false,
                'temperature' => 0.3,
                'maxTokens' => 1000,
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'text' => $this->promt,
                ],
                [
                    'role' => 'user',
                    'text' => $this->text,
                ]
            ],
        ];
        return $json;
    }

    public function getResult()
    {
        $result = $this->sendRequest();
        $aiText = $this->parseResponse($result);

        print_r($aiText);
        exit();
        return $aiText;
    }

    protected function parseResponse(array $data)
    {
        if (!isset($data['alternatives'])) {
            throw new \Exception('В ответе отсутствует параметр alternatives');
        }

        if (!isset($data['alternatives'][0]['message'])) {
            throw new \Exception('В ответе отсутствует параметр alternatives.0.message');
        }

        $jsonText = $data['alternatives'][0]['message']['text'];
        if (!$jsonText) {
            throw new \Exception('Пустой ответ: '.json_encode($data));
        }

        $jsonText = str_replace('```', '', $jsonText);

        return json_decode($jsonText, true);
    }

    protected function sendRequest()
    {
        $headers = $this->getHeaders();
        $json = $this->generateJson();

        $response = Http::withHeaders($headers)->post($this->url, $json);

        if ($response->status() !== 200) {
            throw new \Exception('Не удалось отправить запрос: '.$response->body());
        }

        if (!$response->json()['result']) {
            throw new \Exception('Не удалось получить ответ: '.$response->body());
        }

        return $response->json()['result'];
    }
}
