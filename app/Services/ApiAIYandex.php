<?php

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use danog\MadelineProto\Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    protected bool $aiDebug = false;

    protected bool $aiLogging = false;

    public function __construct()
    {
        $this->aiDebug = config('services.ai.debug');
        $this->aiLogging = config('services.ai.logging');
    }

    protected function getKeyRows(ApiDataTypeEnum $isCompany)
    {
        if ($isCompany === ApiDataTypeEnum::Company) {
            return [
                'type'          => ['id' => 'ai_type', 'type' => 'string'],
                'reason'        => ['id' => 'ai_reason', 'type' => 'string'],
                'position'      => ['id' => 'position', 'type' => 'string'],
                'company_name'  => ['id' => 'company_name', 'type' => 'string'],
                'min_price'     => ['id' => 'min_price', 'type' => 'price'],
                'max_price'     => ['id' => 'max_price', 'type' => 'price'],
                'duty'          => ['id' => 'duty', 'type' => 'string'],
                'requirement'   => ['id' => 'requirement', 'type' => 'string'],
                'work_schedule' => ['id' => 'work_schedule', 'type' => 'string'],
                'type_of_work'  => ['id' => 'type_of_work', 'type' => 'string'],
                'description'   => ['id' => 'description', 'type' => 'string'],
                'period'        => ['id' => 'period', 'type' => 'string'],
                'extra_conditions'  => ['id' => 'extra_conditions', 'type' => 'string'],
                'contact_info'      => ['id' => 'contact_info', 'type' => 'array_string'],
            ];
        } elseif ($isCompany === ApiDataTypeEnum::Specialist) {
            return [
                'type'              => ['id' => 'ai_type', 'type' => 'string'],
                'reason'            => ['id' => 'ai_reason', 'type' => 'string'],
                'experience'        => ['id' => 'experience', 'type' => 'string'],
                'soft_experience'   => ['id' => 'soft_experience', 'type' => 'string'],
                'education'         => ['id' => 'education', 'type' => 'string'],
                'work_schedule'     => ['id' => 'work_schedule', 'type' => 'string'],
                'total_work_project'=> ['id' => 'total_work_project', 'type' => 'string'],
                'type_of_work'      => ['id' => 'type_of_work', 'type' => 'string'],
                'price_by_hour'     => ['id' => 'price_by_hour', 'type' => 'price'],
                'price_by_project'  => ['id' => 'price_by_project', 'type' => 'price'],
                'price_by_month'    => ['id' => 'price_by_month', 'type' => 'price'],
                'about'             => ['id' => 'about', 'type' => 'string'],
                'spec_requirements' => ['id' => 'spec_requirements', 'type' => 'string'],
                'link_resume'       => ['id' => 'link_resume', 'type' => 'string'],
                'contact_info'      => ['id' => 'contact_info', 'type' => 'array_string'],
            ];
        } elseif ($isCompany === ApiDataTypeEnum::Builder) {
            return [
                'type'              => ['id' => 'ai_type', 'type' => 'string'],
                'reason'            => ['id' => 'ai_reason', 'type' => 'string'],
                'experience'        => ['id' => 'experience', 'type' => 'string'],
                'soft_experience'   => ['id' => 'soft_experience', 'type' => 'string'],
                'education'         => ['id' => 'education', 'type' => 'string'],
                'work_schedule'     => ['id' => 'work_schedule', 'type' => 'string'],
                'total_work_project'=> ['id' => 'total_work_project', 'type' => 'string'],
                'type_of_work'      => ['id' => 'type_of_work', 'type' => 'string'],
                'price_by_hour'     => ['id' => 'price_by_hour', 'type' => 'price'],
                'price_by_project'  => ['id' => 'price_by_project', 'type' => 'price'],
                'price_by_month'    => ['id' => 'price_by_month', 'type' => 'price'],
                'about'             => ['id' => 'about', 'type' => 'string'],
                'spec_requirements' => ['id' => 'spec_requirements', 'type' => 'string'],
                'link_resume'       => ['id' => 'link_resume', 'type' => 'string'],
                'contact_info'      => ['id' => 'contact_info', 'type' => 'array_string'],
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
                'maxTokens' => 1200,
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

    public function getResult(ApiDataTypeEnum $isCompany): array
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

            if ($row['type'] == 'price') {
                $modelRows[$row['id']] = (int)$modelRows[$row['id']]*100; // Сумма в копейках
            } elseif ($row['type'] == 'integer') {
                $modelRows[$row['id']] = (int)$modelRows[$row['id']];
            } elseif ($row['type'] == 'array') {
                $modelRows[$row['id']] = $modelRows[$row['id']];
            } elseif ($row['type'] == 'array_string') {
                $tmp = [];
                $arr = $modelRows[$row['id']][0] ?? $modelRows[$row['id']];

                foreach ($arr as $key => $val) {
                    if ($val) {
                        $tmp[] = $key.': '.(is_array($val) ? implode('; ', $val) : $val);
                    }
                }
                $modelRows[$row['id']] = implode('; ', $tmp);
            } elseif ($row['type'] == 'bool') {
                $modelRows[$row['id']] = (bool)$modelRows[$row['id']];
            } else {
                if(is_array($modelRows[$row['id']])) {
                    $modelRows[$row['id']] = implode('; ', $modelRows[$row['id']]);
                }
                $modelRows[$row['id']] = trim($modelRows[$row['id']]);
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

        $this->logging($this->text);
        $response = Http::withHeaders($headers)->post($this->url, $json);

        if ($response->status() !== 200) {
            throw new \Exception('Не удалось отправить запрос: '.$response->body());
        }

        if (!$response->json()['result']) {
            throw new \Exception('Не удалось получить ответ: '.$response->body());
        }

        $this->logging($response->json()['result']);

        return $response->json()['result'];
    }

    public function logging(mixed $text, bool $isError = false)
    {
        if (!$this->aiLogging) {
            return false;
        }

        if ($isError === true) {
            Log::channel('ai_debug')->error($text);
        } else {
            Log::channel('ai_debug')->info($text);
        }
    }

    public function getFolderList()
    {
        $headers = $this->getHeaders();
        $url = 'https://resource-manager.api.cloud.yandex.net/resource-manager/v1/folders';
        $response = Http::withHeaders($headers)->get($url);

        //dd($response->body());
    }
}
