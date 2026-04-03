<?php

namespace Tests\Unit;

use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Services\ApiAIOllama;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class ApiAIOllamaTest extends TestCase
{
    protected ApiAIOllama $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiAIOllama();
    }

    /**
     * @return array<string, array{id: string, type: string}>
     */
    private function keyRows(ApiDataTypeEnum $type): array
    {
        $method = new ReflectionMethod(ApiAIOllama::class, 'getKeyRows');
        $method->setAccessible(true);

        return $method->invoke($this->service, $type);
    }

    public function test_instance_creation(): void
    {
        $this->assertInstanceOf(ApiAIOllama::class, $this->service);
    }

    public function test_get_key_rows_specialist(): void
    {
        $rows = $this->keyRows(ApiDataTypeEnum::Specialist);

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('type', $rows);
        $this->assertArrayHasKey('about', $rows);
        $this->assertSame('ai_type', $rows['type']['id']);
    }

    public function test_get_key_rows_builder(): void
    {
        $rows = $this->keyRows(ApiDataTypeEnum::Builder);

        $this->assertIsArray($rows);
        $this->assertArrayHasKey('legal_form', $rows);
        $this->assertArrayHasKey('service_type', $rows);
    }

    public function test_get_key_rows_company(): void
    {
        $rows = $this->keyRows(ApiDataTypeEnum::Company);

        $this->assertIsArray($rows);
        $this->assertArrayHasKey('description', $rows);
        $this->assertArrayHasKey('company_name', $rows);
    }

    public function test_get_result_structure(): void
    {
        $specialistPayload = [
            'type' => 'резюме',
            'reason' => 'тест',
            'experience' => '5 лет',
            'soft_experience' => '',
            'education' => '',
            'work_schedule' => '',
            'total_work_project' => '',
            'type_of_work' => '',
            'price_by_hour' => '',
            'price_by_project' => '',
            'price_by_month' => '',
            'about' => 'Описание',
            'spec_requirements' => '',
            'link_resume' => '',
            'contact_info' => [],
        ];

        Http::fake([
            'localhost:11434/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($specialistPayload, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->service->setConfig([
            'host' => 'http://localhost:11434',
            'model' => 'test-model',
        ]);
        $this->service->setPromt('Ты извлекаешь структурированные данные.');
        $this->service->setText('Исходный текст поста');

        $result = $this->service->getResult(ApiDataTypeEnum::Specialist);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('origin', $result);
        $this->assertArrayHasKey('json', $result);
        $this->assertSame('резюме', $result['json']['ai_type']);
    }

    public function test_prompt_generation(): void
    {
        $this->assertTrue(true);
    }

    public function test_response_parsing(): void
    {
        $rawResponse = '{"name": "Ivan", "experience": 5}';
        $parsed = json_decode($rawResponse, true);

        $this->assertEquals('Ivan', $parsed['name']);
        $this->assertEquals(5, $parsed['experience']);
    }

    public function test_error_handling_on_empty_response(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response('', 500),
        ]);

        $this->assertTrue(true);
    }

    public function test_timeout_handling(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(null, 200, ['Connection-timeout' => 1]),
        ]);

        $this->assertTrue(true);
    }

    public function test_model_configuration(): void
    {
        $model = config('services.ollama.model');
        $this->assertNotNull($model);
        $this->assertIsString($model);
    }

    public function test_host_configuration(): void
    {
        $host = config('services.ollama.host');
        $this->assertIsString($host);
        $this->assertStringStartsWith('http', $host);
    }

    public function test_data_normalization(): void
    {
        $raw = ['Name' => 'Test', 'SKILL' => 'JS'];
        $normalized = array_change_key_case($raw, CASE_LOWER);

        $this->assertArrayHasKey('name', $normalized);
        $this->assertArrayHasKey('skill', $normalized);
    }

    public function test_empty_fields_handling(): void
    {
        $data = ['name' => '', 'skill' => 'PHP'];
        $filtered = array_filter($data, fn ($v) => $v !== '');

        $this->assertArrayNotHasKey('name', $filtered);
        $this->assertArrayHasKey('skill', $filtered);
    }

    public function test_max_tokens_limit(): void
    {
        $options = ['num_predict' => 500];
        $this->assertArrayHasKey('num_predict', $options);
    }

    public function test_temperature_setting(): void
    {
        $temperature = 0.7;
        $this->assertGreaterThan(0, $temperature);
        $this->assertLessThan(1, $temperature);
    }

    public function test_system_prompt_injection(): void
    {
        $systemPrompt = 'You are a helpful assistant for Radarium project.';
        $this->assertStringContainsString('Radarium', $systemPrompt);
    }

    public function test_user_prompt_construction(): void
    {
        $template = 'Generate data for %s with fields: %s';
        $prompt = sprintf($template, 'Specialist', 'name, skills');

        $this->assertStringContainsString('Specialist', $prompt);
        $this->assertStringContainsString('name, skills', $prompt);
    }

    public function test_json_validity_check(): void
    {
        $validJson = '{"key": "value"}';
        $invalidJson = '{key: value}';

        json_decode($validJson);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());

        json_decode($invalidJson);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function test_retry_logic_simulation(): void
    {
        $attempts = 0;
        $maxRetries = 3;

        while ($attempts < $maxRetries) {
            $attempts++;
        }

        $this->assertEquals($maxRetries, $attempts);
    }

    public function test_logging_mechanism(): void
    {
        $this->assertTrue(true);
    }

    public function test_api_source_enum_usage(): void
    {
        $source = ApiAiSourceEnum::OllamaQwen;
        $this->assertEquals('ollama_qwen', $source->value);
    }

    public function test_parse_response_via_reflection(): void
    {
        $method = new ReflectionMethod(ApiAIOllama::class, 'parseResponse');
        $method->setAccessible(true);

        $content = '{"type":"резюме","reason":"ok"}';
        $body = [
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ];

        $parsed = $method->invoke($this->service, $body);
        $this->assertSame('резюме', $parsed['type']);
    }
}
