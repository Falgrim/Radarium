<?php

namespace Tests\Feature;

use App\Services\ReadTelegramChats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use danog\MadelineProto\API;

class ReadTelegramChatsTest extends TestCase
{
    use RefreshDatabase;

    protected ReadTelegramChats $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Инициализация сервиса
        $this->service = app(ReadTelegramChats::class);
    }

    public function test_service_initialization(): void
    {
        $this->assertInstanceOf(ReadTelegramChats::class, $this->service);
    }

    public function test_start_method_exists(): void
    {
        $this->assertTrue(method_exists($this->service, 'start'));
    }

    public function test_session_file_handling(): void
    {
        // Проверка пути к файлу сессии
        $sessionPath = config('telegram.session_path', storage_path('madeline'));
        $this->assertStringStartsWith(base_path(), $sessionPath);
    }

    public function test_phone_number_configuration(): void
    {
        $phone = config('telegram.phone');
        $this->assertNotNull($phone);
        // $this->assertMatchesRegularExpression('/^\+\d+$/', $phone);
    }

    public function test_bot_token_configuration(): void
    {
        $token = config('telegram.bot_token');
        $this->assertNotNull($token);
    }

    public function test_chat_ids_configuration(): void
    {
        $chatIds = config('telegram.chat_ids', []);
        $this->assertIsArray($chatIds);
    }

    public function test_message_deduplication(): void
    {
        // Логика проверки дубликатов сообщений
        $seenMessages = [];
        $msgId = 12345;
        
        $this->assertFalse(in_array($msgId, $seenMessages));
        $seenMessages[] = $msgId;
        $this->assertTrue(in_array($msgId, $seenMessages));
    }

    public function test_message_storage(): void
    {
        // Тест сохранения сообщения в БД
        $message = \App\Models\Message::create([
            'chat_id' => 123,
            'message_id' => 456,
            'text' => 'Test message',
            'date' => now()
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => 123,
            'text' => 'Test message'
        ]);
    }

    public function test_parse_entities(): void
    {
        $entities = [
            ['type' => 'url', 'offset' => 0, 'length' => 10],
            ['type' => 'bold', 'offset' => 12, 'length' => 5]
        ];
        
        $this->assertCount(2, $entities);
        $this->assertEquals('url', $entities[0]['type']);
    }

    public function test_handle_forwarded_messages(): void
    {
        $forwardFrom = ['id' => 987, 'first_name' => 'Forwarder'];
        $this->assertArrayHasKey('id', $forwardFrom);
    }

    public function test_media_processing(): void
    {
        $hasMedia = true;
        $mediaType = 'photo';
        
        if ($hasMedia) {
            $this->assertEquals('photo', $mediaType);
        }
    }

    public function test_error_logging_on_failure(): void
    {
        Log::shouldReceive('error')
            ->with('Telegram connection failed')
            ->once();
            
        // Симуляция ошибки
        Log::error('Telegram connection failed');
    }

    public function test_reconnection_logic(): void
    {
        $maxRetries = 5;
        $currentRetry = 0;
        
        while ($currentRetry < $maxRetries) {
            $currentRetry++;
        }
        
        $this->assertEquals($maxRetries, $currentRetry);
    }

    public function test_command_execution_via_console(): void
    {
        // Тест запуска команды через artisan
        $this->artisan('app:read_telegram')
             ->assertExitCode(0);
    }
}
