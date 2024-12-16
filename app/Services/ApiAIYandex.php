<?php

namespace App\Services;

use App\DTO\MedBotCreateClientDTO;
use App\DTO\MedBotDeleteClientDTO;
use App\Exceptions\MedBotException;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;

// https://yandex.cloud/ru/docs/foundation-models/quickstart/yandexgpt#api_2

class ApiAIYandex
{
    protected string $bearer;

    protected string $folderId;

    protected string $promt;

    protected string $text;

    protected string $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

    public function __construct()
    {
    }

    public function setConfig(array $config)
    {
        if (!isset($config['Bearer'])) {
            throw new Exception('Не указан Bearer параметр');
        }

        if (!isset($config['Folder_id'])) {
            throw new Exception('Не указан Folder_id параметр');
        }

        $this->bearer = $config['Bearer'];
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
            'Content-Type: application/json',
            'Authorization: Bearer '.$this->bearer,
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
        //return json_encode($json);
    }

    public function sendRequest()
    {
        $headers = $this->getHeaders();
        $json = $this->generateJson();

        $response = Http::post($this->url, $json)->withHeaders($headers);

        print_r($response);
        exit();

        if ($response->status() !== 200) {
            //throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        if ($response->json()['code'] != 'create_client') {
            //throw new MedBotException('Не удалось создать клиента: '.($response->json()['message'] ?? $response->json()['code']));
        }

        return $response->json();
    }
}
