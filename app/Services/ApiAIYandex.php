<?php

namespace App\Services;

use App\Enum\IsCompanyEnum;
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

    protected function getKeyRows(IsCompanyEnum $isCompany)
    {
        if ($isCompany === IsCompanyEnum::Company) {
            return [
                'Тип сообщения'             => ['id' => 'ai_type', 'type' => 'string'],
                'Почему такой тип'          => ['id' => 'ai_reason', 'type' => 'string'],
                'Должность'                 => ['id' => 'position', 'type' => 'string'],
                'Название компании'         => ['id' => 'company_name', 'type' => 'string'],
                'Предлагаемый оклад (от)'   => ['id' => 'min_price', 'type' => 'price'],
                'Предлагаемый оклад (до)'   => ['id' => 'max_price', 'type' => 'price'],
                'Обязанности'               => ['id' => 'duty', 'type' => 'string'],
                'Требования'                => ['id' => 'requirement', 'type' => 'string'],
                'График'                    => ['id' => 'work_schedule', 'type' => 'string'],
                'Тип работы'                => ['id' => 'type_of_work', 'type' => 'string'],
                'Описание проекта'          => ['id' => 'description', 'type' => 'string'],
                'Срок найма'                => ['id' => 'period', 'type' => 'string'],
                'Дополнительные условия'    => ['id' => 'extra_conditions', 'type' => 'string'],
            ];
        } elseif ($isCompany === IsCompanyEnum::Private) {
            return [
                'Тип сообщения'                 => ['id' => 'ai_type', 'type' => 'string'],
                'Почему такой тип'              => ['id' => 'ai_reason', 'type' => 'string'],
                'Опыт работы по специальности'  => ['id' => 'experience', 'type' => 'string'],
                'Владение ПО'                   => ['id' => 'soft_experience', 'type' => 'string'],
                'Образование'                   => ['id' => 'education', 'type' => 'string'],
                'Требуемый график работы'       => ['id' => 'work_schedule', 'type' => 'string'],
                'Общая продолжительность работы (за проект)' => ['id' => 'total_work_project', 'type' => 'string'],
                'Тип работы'                    => ['id' => 'type_of_work', 'type' => 'string'],
                'Желаемая оплата за час'        => ['id' => 'price_by_hour', 'type' => 'price'],
                'Желаемая оплата за проект'     => ['id' => 'price_by_project', 'type' => 'price'],
                'Желаемая оплата за месяц'      => ['id' => 'price_by_month', 'type' => 'price'],
                'О себе'                        => ['id' => 'about', 'type' => 'string'],
                'Спец. требования'              => ['id' => 'spec_requirements', 'type' => 'string'],
                'Ссылка на резюме'              => ['id' => 'link_resume', 'type' => 'string'],
            ];
        }
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

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Api-Key '.$this->apiToken,
        ];
    }

    protected function generateJson(): array
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

    public function getResult(IsCompanyEnum $isCompany): array
    {
        $result = $this->sendRequest();
        $aiText = $this->parseResponse($result);

        $keyRows = $this->getKeyRows($isCompany);
        $modelRows = [];
        foreach ($keyRows as $id => $row) {
            $modelRows[$row['id']] = $aiText[$id] ?? null;
            if (is_null($modelRows[$row['id']])) {
                continue;
            }

            if ($row['type'] == 'string') {
                if(is_array($modelRows[$row['id']])) {
                    $modelRows[$row['id']] = implode('-|||-', $modelRows[$row['id']]);
                }
                $modelRows[$row['id']] = trim($modelRows[$row['id']]);
            } elseif ($row['type'] == 'price') {
                $modelRows[$row['id']] = (int)$modelRows[$row['id']]*100; // Сумма в копейках
            } elseif ($row['type'] == 'integer') {
                $modelRows[$row['id']] = (int)$modelRows[$row['id']];
            }
        }

        return ['origin' => $result['alternatives'][0]['message']['text'], 'json' => $modelRows];
    }

    protected function parseResponse(array $data): array
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

        return json_decode(trim($jsonText), true);
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
