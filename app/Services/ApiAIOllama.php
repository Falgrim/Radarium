<?php

namespace App\Services;

use App\Enum\ApiDataTypeEnum;
use App\Services\BuilderNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiAIOllama
{
    protected string $host;

    protected string $model;

    protected string $promt;

    protected string $text;

    protected bool $aiDebug = false;

    protected bool $aiLogging = false;

    protected string $logPrefix = '[Ollama/Qwen]';

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
                'service_type'      => ['id' => 'service_type_raw', 'type' => 'string'],
                'specialities'      => ['id' => 'service_types', 'type' => 'array'],
                'object_types'      => ['id' => 'object_types', 'type' => 'array'],
                'performer_raw'     => ['id' => 'performer_type_raw', 'type' => 'string'],
                'performer_type'    => ['id' => 'performer_type', 'type' => 'string'],
                'equipment_skills'  => ['id' => 'equipment_skills_json', 'type' => 'array'],
                'legal_form'        => ['id' => 'legal_form', 'type' => 'string'],
                'location_city'     => ['id' => 'location_city', 'type' => 'string'],
                'location_region'   => ['id' => 'location_region', 'type' => 'string'],
                'price_comment'     => ['id' => 'price_comment', 'type' => 'string'],
            ];
        }
    }

    public function setConfig(array $config)
    {
        $this->host = $config['host']
            ?? config('services.ollama.host', 'http://79.175.45.41:11434');

        $this->model = $config['model']
            ?? config('services.ollama.model', 'qwen2.5:7b-instruct-q4_K_M');
    }

    public function setPromt(string $promt)
    {
        $this->promt = $promt;
    }

    public function setText(string $text)
    {
        $this->text = $text;
    }

    protected function getUrl(): string
    {
        return rtrim($this->host, '/') . '/v1/chat/completions';
    }

    protected function generateJson(): array
    {
        return [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->promt,
                ],
                [
                    'role' => 'user',
                    'content' => $this->text,
                ],
            ],
            'temperature' => 0.3,
            'stream' => false,
        ];
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
                $modelRows[$row['id']] = BuilderNormalizer::cleanPrice((string)$modelRows[$row['id']]);
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

        return ['origin' => $result['choices'][0]['message']['content'], 'json' => $modelRows];
    }

    protected function parseResponse(array $data): array
    {
        if (!isset($data['choices'])) {
            throw new \Exception($this->logPrefix . ' В ответе отсутствует параметр choices');
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception($this->logPrefix . ' В ответе отсутствует параметр choices.0.message.content');
        }

        $jsonText = $data['choices'][0]['message']['content'];
        if (!$jsonText) {
            throw new \Exception($this->logPrefix . ' Пустой ответ: ' . json_encode($data));
        }

        $jsonText = str_replace('```json', '', $jsonText);
        $jsonText = str_replace('```', '', $jsonText);

        return json_decode(trim($jsonText), true);
    }

    protected function sendRequest(): array
    {
        $url = $this->getUrl();
        $json = $this->generateJson();

        $this->logging($this->text);
        $response = Http::timeout(120)->post($url, $json);

        if ($response->status() !== 200) {
            throw new \Exception($this->logPrefix . ' Не удалось отправить запрос: ' . $response->body());
        }

        $body = $response->json();

        if (!isset($body['choices'])) {
            throw new \Exception($this->logPrefix . ' Не удалось получить ответ: ' . $response->body());
        }

        $this->logging($body);

        return $body;
    }

    public function logging(mixed $text, bool $isError = false)
    {
        if (!$this->aiLogging) {
            return false;
        }

        $message = is_string($text) ? $this->logPrefix . ' ' . $text : $text;

        if ($isError === true) {
            Log::channel('ai_debug')->error($message);
        } else {
            Log::channel('ai_debug')->info($message);
        }
    }
}
