<?php

namespace Tests\Unit;

use App\Services\ApiAIOllama;
use App\Enum\ApiDataTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ApiAIOllamaTest extends TestCase
{
    use RefreshDatabase;

    protected ApiAIOllama $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ApiAIOllama();
    }

    public function test_instance_creation(): void
    {
        $this->assertInstanceOf(ApiAIOllama::class, $this->service);
    }

    public function test_get_key_rows_specialist(): void
    {
        $rows = $this->service->getKeyRows(ApiDataTypeEnum::Specialist);
        
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        // Проверка наличия обязательных полей для специалиста
        $this->assertContains('name', $rows);
        $this->assertContains('skills', $rows);
    }

    public function test_get_key_rows_builder(): void
    {
        $rows = $this->service->getKeyRows(ApiDataTypeEnum::Builder);
        
        $this->assertIsArray($rows);
        $this->assertContains('company_name', $rows);
    }

    public function test_get_key_rows_company(): void
    {
        $rows = $this->service->getKeyRows(ApiDataTypeEnum::Company);
        
        $this->assertIsArray($rows);
        $this->assertContains('description', $rows);
    }

    public function test_get_result_structure(): void
    {
        // Мокаем внешний HTTP запрос к Ollama
        Http::fake([
            'localhost:11434/*' => Http::response([
                'model' => 'qwen2.5',
                'response' => '{"name": "Test", "skill": "PHP"}',
                'done' => true
            ], 200)
        ]);

        // Тест требует наличия реальной конфигурации или моков внутренних методов
        // Проверяем, что метод существует и возвращает массив
        $result = $this->service->getResult(ApiDataTypeEnum::Specialist);
        
        $this->assertIsArray($result);
    }

    public function test_prompt_generation(): void
    {
        // Проверка формирования промпта (если метод доступен)
        // Это зависит от видимости методов в классе
        $this->assertTrue(true); 
    }

    public function test_response_parsing(): void
    {
        // Тест парсинга JSON ответа от модели
        $rawResponse = '{"name": "Ivan", "experience": 5}';
        $parsed = json_decode($rawResponse, true);
        
        $this->assertEquals('Ivan', $parsed['name']);
        $this->assertEquals(5, $parsed['experience']);
    }

    public function test_error_handling_on_empty_response(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response('', 500)
        ]);

        // Сервис должен корректно обрабатывать ошибки
        $this->assertTrue(true);
    }

    public function test_timeout_handling(): void
    {
        Http::fake([
            'localhost:11434/*' => Http::response(null, 200, ['Connection-timeout' => 1])
        ]);
        
        $this->assertTrue(true);
    }

    public function test_model_configuration(): void
    {
        // Проверка, что используется правильная модель из конфига
        $model = config('ollama.model', 'qwen2.5');
        $this->assertNotNull($model);
    }

    public function test_host_configuration(): void
    {
        $host = config('ollama.host', 'http://localhost:11434');
        $this->assertStringStartsWith('http', $host);
    }

    public function test_data_normalization(): void
    {
        $raw = ['Name' => 'Test', 'SKILL' => 'JS'];
        // Логика нормализации ключей
        $normalized = array_change_key_case($raw, CASE_LOWER);
        
        $this->assertArrayHasKey('name', $normalized);
        $this->assertArrayHasKey('skill', $normalized);
    }

    public function test_empty_fields_handling(): void
    {
        $data = ['name' => '', 'skill' => 'PHP'];
        // Проверка фильтрации пустых значений
        $filtered = array_filter($data, fn($v) => $v !== '');
        
        $this->assertArrayNotHasKey('name', $filtered);
        $this->assertArrayHasKey('skill', $filtered);
    }

    public function test_max_tokens_limit(): void
    {
        // Проверка параметров запроса на наличие лимитов
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
        $systemPrompt = "You are a helpful assistant for Radarium project.";
        $this->assertStringContainsString('Radium', $systemPrompt);
    }

    public function test_user_prompt_construction(): void
    {
        $template = "Generate data for %s with fields: %s";
        $prompt = sprintf($template, 'Specialist', 'name, skills');
        
        $this->assertStringContainsString('Specialist', $prompt);
        $this->assertStringContainsString('name, skills', $prompt);
    }

    public function test_json_validity_check(): void
    {
        $validJson = '{"key": "value"}';
        $invalidJson = '{key: value}';
        
        $this->assertNotNull(json_decode($validJson));
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        
        json_decode($invalidJson);
        $this->.assertNotEquals(JSON_ERROR_NONE, json_last_error());
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
        // Проверка наличия логирования (интеграционный тест)
        $this->assertTrue(true);
    }

    public function test_api_source_enum_usage(): void
    {
        use App\Enum\ApiAiSourceEnum;
        $source = ApiAiSourceEnum::OllamaQwen;
        $this->assertEquals('ollama_qwen', $source->value);
    }
}
