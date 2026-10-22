<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiChannelStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ParsingTestRunStatus;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ParsingTestMessage;
use App\Models\ParsingTestRun;
use App\Services\ParsingAccuracyTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParsingAccuracyTestServiceTest extends TestCase
{
    use RefreshDatabase;

    private const OFFER = 'Мы бригада маляров, делаем квартиры под ключ и выезжаем на объект.';

    public function createApplication()
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        return parent::createApplication();
    }

    public function test_pass1_rejection_is_not_a_parse_error_and_skips_card_extraction(): void
    {
        $post = $this->makePost(self::OFFER, 501);
        $run = $this->makeRun(1);
        Http::fake([
            'ollama.test/*' => Http::response($this->chat('{"type":"мусор"}'), 200),
        ]);

        app(ParsingAccuracyTestService::class)->execute($run);

        $run->refresh();
        $post->refresh();

        $this->assertSame(ParsingTestRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(0, $run->target_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(0, $run->extraction_error_count);
        $this->assertSame(0, ParsingTestMessage::query()->count());
        $this->assertSame(ApiChannelPostStatusEnum::InQueue, $post->ai_parse_status);
        Http::assertSentCount(1);
    }

    public function test_valid_card_is_stored_for_a_selected_offer(): void
    {
        $post = $this->makePost(self::OFFER, 503);
        $run = $this->makeRun(1);
        Http::fake([
            'ollama.test/*' => Http::sequence()
                ->push($this->chat('{"type":"предложение услуги"}'), 200)
                ->push($this->chat('{"type":"предложение услуги","reason":"бригада маляров"}'), 200),
        ]);

        app(ParsingAccuracyTestService::class)->execute($run);

        $run->refresh();
        $message = ParsingTestMessage::query()->first();

        $this->assertSame(ParsingTestRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->target_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(0, $run->extraction_error_count);
        $this->assertNotNull($message);
        $this->assertSame($post->id, $message->api_channel_post_id);
        $this->assertSame('предложение услуги', $message->ai_type);
        $this->assertNull($message->extraction_error);
        $this->assertStringContainsString('бригада', (string) $message->ai_reason);
        Http::assertSentCount(2);
    }

    public function test_card_extraction_failure_keeps_the_selected_message(): void
    {
        $post = $this->makePost(self::OFFER, 502);
        $run = $this->makeRun(1);
        Http::fake([
            'ollama.test/*' => Http::sequence()
                ->push($this->chat('{"type":"предложение услуги"}'), 200)
                ->push($this->chat('это не json'), 200),
        ]);

        app(ParsingAccuracyTestService::class)->execute($run);

        $run->refresh();
        $message = ParsingTestMessage::query()->first();

        $this->assertSame(ParsingTestRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(1, $run->target_count);
        $this->assertSame(0, $run->error_count);
        $this->assertSame(1, $run->extraction_error_count);
        $this->assertNotNull($message);
        $this->assertSame($post->id, $message->api_channel_post_id);
        $this->assertSame('предложение услуги', $message->ai_type);
        $this->assertNotSame('', trim((string) $message->extraction_error));
        $this->assertStringContainsString('JSON', (string) $run->last_error);
        Http::assertSentCount(2);
    }

    public function test_cancel_request_stops_the_run_between_messages(): void
    {
        $this->makePost(self::OFFER, 601);
        $this->makePost(self::OFFER.' Второй автор.', 602);
        $run = $this->makeRun(2);
        Http::fake(function () use ($run) {
            ParsingTestRun::query()->whereKey($run->id)->update([
                'cancel_requested_at' => now(),
            ]);

            return Http::response($this->chat('{"type":"мусор"}'), 200);
        });

        app(ParsingAccuracyTestService::class)->execute($run);

        $run->refresh();

        $this->assertSame(ParsingTestRunStatus::Cancelled, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertSame('Остановлен вручную', $run->error_message);
        $this->assertSame(2, $run->sampled_count);
        $this->assertSame(1, $run->processed_count);
        $this->assertNull($run->pid);
        Http::assertSentCount(1);
    }

    public function test_queued_cancel_is_final_before_the_worker_starts(): void
    {
        $run = $this->makeRun(1);

        app(ParsingAccuracyTestService::class)->requestCancel($run);

        $run->refresh();

        $this->assertSame(ParsingTestRunStatus::Cancelled, $run->status);
        $this->assertNotNull($run->cancel_requested_at);
        $this->assertSame('Остановлен вручную', $run->error_message);
    }

    public function test_page_refresh_closes_a_stop_whose_process_is_already_gone(): void
    {
        $run = $this->makeRun(1);
        $run->update([
            'status' => ParsingTestRunStatus::Running,
            'cancel_requested_at' => now()->subMinutes(4),
            'pid' => 999999,
            'started_at' => now()->subMinutes(4),
        ]);

        app(ParsingAccuracyTestService::class)->releaseStaleRuns();

        $run->refresh();

        $this->assertSame(ParsingTestRunStatus::Cancelled, $run->status);
        $this->assertSame('Остановлен вручную', $run->error_message);
        $this->assertNotNull($run->finished_at);
    }

    private function makeRun(int $sampleSize): ParsingTestRun
    {
        return ParsingTestRun::query()->create([
            'status' => ParsingTestRunStatus::Queued,
            'industry' => 'builder',
            'source' => ApiChannelSourceEnum::Telegram->value,
            'sample_size' => $sampleSize,
            'model' => 'qwen3.5:9b',
            'prompt_label' => 'Тестовый промпт',
            'prompt_body' => 'Верни JSON. {{SPECIALITIES_LIST}}',
            'provider_options' => [
                'host' => 'http://ollama.test',
                'model' => 'qwen3.5:9b',
            ],
        ]);
    }

    private function makePost(string $text, int $authorId): ApiChannelPost
    {
        $channel = ApiChannel::query()->create([
            'title' => 'Чат '.$authorId,
            'link' => 'https://t.me/chat'.$authorId,
            'description' => 'тест',
            'channel_source' => ApiChannelSourceEnum::Telegram,
            'status' => ApiChannelStatusEnum::Active,
            'is_company' => ApiDataTypeEnum::Builder,
        ]);

        return ApiChannelPost::query()->create([
            'api_channel_id' => $channel->id,
            'user_login' => 'author'.$authorId,
            'api_post_user_id' => $authorId,
            'post_id' => (string) $authorId,
            'post_date' => now(),
            'post' => $text,
            'ai_parse_status' => ApiChannelPostStatusEnum::InQueue,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chat(string $content): array
    {
        return [
            'choices' => [
                ['message' => ['content' => $content]],
            ],
        ];
    }
}
