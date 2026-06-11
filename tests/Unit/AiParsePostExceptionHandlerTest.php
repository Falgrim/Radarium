<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enum\ApiChannelPostStatusEnum;
use App\Exceptions\AiProviderUnavailableException;
use App\Models\ApiChannelPost;
use App\Services\AiParsePostExceptionHandler;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
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

    public function test_provider_unavailable_keeps_post_in_queue(): void
    {
        $post = new ApiChannelPost([
            'id' => 42,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue,
        ]);

        $command = $this->makeCommand();

        $this->handler->handle(
            $post,
            new AiProviderUnavailableException('[Ollama/Qwen] cURL error 7: No route to host'),
            null,
            $command
        );

        $this->assertSame(ApiChannelPostStatusEnum::InQueue, $post->ai_parse_status);
        $this->assertNull($post->ai_result);
        $this->assertNull($post->ai_date);
    }

    public function test_connection_exception_is_treated_as_unavailable(): void
    {
        $request = Request::create('http://127.0.0.1:11434/v1/chat/completions', 'POST');
        $exception = new ConnectionException($request);

        $this->assertTrue($this->handler->isProviderUnavailable($exception));
    }

    public function test_permanent_failure_sets_error_status(): void
    {
        $post = new ApiChannelPost([
            'id' => 43,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue,
        ]);

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
