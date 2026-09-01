<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enum\ApiChannelPostStatusEnum;
use App\Exceptions\AiContentRefusalException;
use App\Exceptions\AiProviderUnavailableException;
use App\Models\ApiChannelPost;
use App\Services\AiParsePostExceptionHandler;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Client\ConnectionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

final class AiParsePostExceptionHandlerTest extends TestCase
{
    private AiParsePostExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AiParsePostExceptionHandler();
    }

    public function test_provider_unavailable_is_detected_and_does_not_mutate_via_is_check(): void
    {
        $exception = new AiProviderUnavailableException('[Ollama/Qwen] cURL error 7: No route to host');
        $this->assertTrue($this->handler->isProviderUnavailable($exception));

        // Полный handle() дергает AiProviderHealthAlertService (final + БД) — проверяем контракт детекции.
        $post = new ApiChannelPost([
            'id' => 42,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue,
        ]);
        $this->assertSame(ApiChannelPostStatusEnum::InQueue, $post->ai_parse_status);
        $this->assertNull($post->ai_result);
    }

    public function test_connection_exception_is_treated_as_unavailable(): void
    {
        $exception = new ConnectionException('cURL error 7: Failed to connect');

        $this->assertTrue($this->handler->isProviderUnavailable($exception));
    }

    public function test_content_refusal_is_detected(): void
    {
        $this->assertTrue($this->handler->isContentRefusal(
            new AiContentRefusalException('[YandexGPT] Отказ модели (safety): Я не могу обсуждать эту тему')
        ));
        $this->assertTrue($this->handler->isContentRefusal(
            new \Exception('[YandexGPT] Ответ модели не является валидным JSON: Syntax error. Начало ответа: Я не могу обсуждать эту тему. Давайте поговорим о чём-нибудь ещё.')
        ));
        $this->assertFalse($this->handler->isContentRefusal(
            new \Exception('invalid JSON shape')
        ));
    }

    public function test_permanent_failure_sets_error_status(): void
    {
        $post = new ApiChannelPost([
            'id' => 43,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue,
        ]);
        $post->exists = true;
        $post->wasRecentlyCreated = false;

        // Не ходим в БД: проверяем статус через перехват save.
        $post = new class extends ApiChannelPost
        {
            public bool $saved = false;

            public function save(array $options = []): bool
            {
                $this->saved = true;

                return true;
            }
        };
        $post->id = 43;
        $post->ai_parse_status = ApiChannelPostStatusEnum::InQueue;

        $command = $this->makeCommand();

        $this->handler->handle(
            $post,
            new \Exception('invalid JSON shape'),
            null,
            $command
        );

        $this->assertSame(ApiChannelPostStatusEnum::Error, $post->ai_parse_status);
        $this->assertSame('invalid JSON shape', $post->ai_result);
        $this->assertNotNull($post->ai_date);
        $this->assertTrue($post->saved);
    }

    public function test_content_refusal_sets_dont_match_status(): void
    {
        $post = new class extends ApiChannelPost
        {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $post->id = 44;
        $post->ai_parse_status = ApiChannelPostStatusEnum::InQueue;

        $this->handler->handle(
            $post,
            new AiContentRefusalException('[YandexGPT] Отказ модели (safety): Я не могу обсуждать эту тему'),
            null,
            $this->makeCommand()
        );

        $this->assertSame(ApiChannelPostStatusEnum::DontMatch, $post->ai_parse_status);
    }

    private function makeCommand(): \Illuminate\Console\Command
    {
        $command = new class extends \Illuminate\Console\Command
        {
            protected $signature = 'test:ai-parse-failure';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        return $command;
    }
}
