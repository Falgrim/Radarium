<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\AdminSystemPromptScope;
use App\Enum\ApiAiSourceEnum;
use App\Enum\ApiAiStatusEnum;
use App\Enum\ApiChannelSourceEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\DictionaryEnum;
use App\Enum\ParsingTestRunStatus;
use App\Exceptions\AiProviderUnavailableException;
use App\Models\AdminSystemPromptPreset;
use App\Models\ApiAi;
use App\Models\ApiChannel;
use App\Models\ApiChannelPost;
use App\Models\ParsingTestMessage;
use App\Models\ParsingTestRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ParsingAccuracyTestService
{
    public const SAMPLE_SIZE = 300;

    public function start(): ParsingTestRun
    {
        $run = $this->openRun();
        $this->spawn($run);

        return $run;
    }

    public function openRun(): ParsingTestRun
    {
        $lock = Cache::lock('parsing-accuracy-test-start', 30);
        if (! $lock->get()) {
            throw new RuntimeException('Тест уже запускается.');
        }

        try {
            $this->releaseStaleRuns();
            if ($this->activeRun() !== null) {
                throw new RuntimeException('Тест уже выполняется.');
            }

            $prompt = $this->resolvePrompt();
            ParsingTestMessage::query()->delete();

            return ParsingTestRun::query()->create([
                'status' => ParsingTestRunStatus::Queued,
                'industry' => 'builder',
                'source' => ApiChannelSourceEnum::Telegram->value,
                'sample_size' => self::SAMPLE_SIZE,
                'model' => $prompt['model'],
                'prompt_label' => $prompt['label'],
                'prompt_body' => $prompt['body'],
                'provider_options' => $prompt['options'],
            ]);
        } finally {
            $lock->release();
        }
    }

    public function execute(ParsingTestRun $run, ?callable $logger = null): void
    {
        $claimed = ParsingTestRun::query()
            ->whereKey($run->id)
            ->where('status', ParsingTestRunStatus::Queued)
            ->whereNull('cancel_requested_at')
            ->update([
                'status' => ParsingTestRunStatus::Running->value,
                'started_at' => now(),
                'error_message' => null,
                'pid' => getmypid(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $run->refresh();

        try {
            $this->process($run, $logger);
            $this->finishRun($run);
            $run->refresh();
            if ($run->status === ParsingTestRunStatus::Cancelled) {
                $logger && $logger('Прогон прерван.');

                return;
            }
            $logger && $logger(sprintf(
                'Готово. Целевых: %d из %d.',
                $run->target_count,
                $run->sampled_count
            ));
        } catch (Throwable $e) {
            if ($this->cancelRequested($run->id)) {
                $this->markCancelled($run);
                $logger && $logger('Прогон прерван.');

                return;
            }

            ParsingTestRun::query()->whereKey($run->id)->update([
                'status' => ParsingTestRunStatus::Failed->value,
                'finished_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'pid' => null,
            ]);
            Log::error('[parsing-accuracy-test] прогон остановлен', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function requestCancel(ParsingTestRun $run): void
    {
        $closed = ParsingTestRun::query()
            ->whereKey($run->id)
            ->where('status', ParsingTestRunStatus::Queued)
            ->update([
                'status' => ParsingTestRunStatus::Cancelled->value,
                'cancel_requested_at' => now(),
                'finished_at' => now(),
                'error_message' => 'Остановлен вручную',
                'pid' => null,
            ]);

        if ($closed === 1) {
            return;
        }

        ParsingTestRun::query()
            ->whereKey($run->id)
            ->where('status', ParsingTestRunStatus::Running)
            ->update([
                'cancel_requested_at' => now(),
            ]);

        $pid = (int) ParsingTestRun::query()->whereKey($run->id)->value('pid');
        $this->signalProcess($pid);
    }

    public function activeRun(): ?ParsingTestRun
    {
        return ParsingTestRun::query()
            ->whereIn('status', [
                ParsingTestRunStatus::Queued->value,
                ParsingTestRunStatus::Running->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * @return list<int>
     */
    public function samplePostIds(int $limit): array
    {
        $limit = max(1, $limit);
        $ids = [];

        $userIds = $this->randomDistinctValues('api_post_user_id', $limit, true);

        foreach ($userIds as $userId) {
            $postId = $this->telegramPostsQuery()
                ->where('api_channel_posts.api_post_user_id', $userId)
                ->inRandomOrder()
                ->value('api_channel_posts.id');
            if ($postId) {
                $ids[] = (int) $postId;
            }
        }

        if (count($ids) >= $limit) {
            return $ids;
        }

        $logins = $this->randomDistinctValues('user_login', $limit - count($ids), false);

        foreach ($logins as $login) {
            $postId = $this->telegramPostsQuery()
                ->whereNull('api_channel_posts.api_post_user_id')
                ->where('api_channel_posts.user_login', $login)
                ->inRandomOrder()
                ->value('api_channel_posts.id');
            if ($postId) {
                $ids[] = (int) $postId;
            }
        }

        return $ids;
    }

    /**
     * @return array{body: string, label: string, model: string, options: array<string, mixed>}
     */
    public function resolvePrompt(): array
    {
        $ai = $this->resolveOllama();
        $options = is_array($ai->options) ? $ai->options : [];
        $model = trim((string) ($options['model'] ?? ''));
        if ($model === '') {
            $model = (string) config('services.ollama.model', 'qwen3.5:9b');
        }

        $admin = app(AiSystemPromptAdminService::class);
        $presets = AdminSystemPromptPreset::query()
            ->where('scope', AdminSystemPromptScope::Builder->value)
            ->where('api_source', ApiAiSourceEnum::OllamaQwen->value)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        foreach ($presets as $preset) {
            $body = trim((string) ($preset->body ?? ''));
            if ($body === '') {
                continue;
            }
            if ($admin->isPresetCurrentlyApplied(ApiDataTypeEnum::Builder, $preset)) {
                return [
                    'body' => $body,
                    'label' => (string) $preset->name,
                    'model' => $model,
                    'options' => $options,
                ];
            }
        }

        $prompts = ApiChannel::query()
            ->where('is_company', ApiDataTypeEnum::Builder)
            ->where('api_ai_id', $ai->id)
            ->whereNotNull('ai_promt')
            ->pluck('ai_promt')
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '');

        if ($prompts->isNotEmpty()) {
            return [
                'body' => (string) $prompts->countBy()->sortDesc()->keys()->first(),
                'label' => 'Промпт источников строителей',
                'model' => $model,
                'options' => $options,
            ];
        }

        $fallback = trim(app(BuilderAiPipelineRuntimeConfig::class)->pass2DefaultPrompt());
        if ($fallback === '') {
            throw new RuntimeException('Системный промпт строителей пуст.');
        }

        return [
            'body' => $fallback,
            'label' => 'Общий промпт Pass 2',
            'model' => $model,
            'options' => $options,
        ];
    }

    public function releaseStaleRuns(): void
    {
        $tail = $this->logTail();
        $message = 'Фоновый процесс не стартовал.';
        if ($tail !== '') {
            $message .= ' '.$tail;
        }

        ParsingTestRun::query()
            ->where('status', ParsingTestRunStatus::Queued)
            ->where('created_at', '<', now()->subMinutes(5))
            ->update([
                'status' => ParsingTestRunStatus::Failed->value,
                'finished_at' => now(),
                'error_message' => mb_substr($message, 0, 2000),
            ]);

        $stopping = ParsingTestRun::query()
            ->where('status', ParsingTestRunStatus::Running)
            ->whereNotNull('cancel_requested_at')
            ->get();

        foreach ($stopping as $stoppingRun) {
            $requestedAt = $stoppingRun->cancel_requested_at;
            $timedOut = $requestedAt !== null && $requestedAt->lte(now()->subMinutes(3));
            if ($timedOut || $this->pidIsGone((int) $stoppingRun->pid)) {
                $this->markCancelled($stoppingRun);
            }
        }

        ParsingTestRun::query()
            ->where('status', ParsingTestRunStatus::Running)
            ->whereNull('cancel_requested_at')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update([
                'status' => ParsingTestRunStatus::Failed->value,
                'finished_at' => now(),
                'pid' => null,
                'error_message' => 'Процесс остановился: нет прогресса больше 30 минут.',
            ]);
    }

    private function process(ParsingTestRun $run, ?callable $logger): void
    {
        $run->update(['note' => 'Выбираю случайные сообщения…']);
        $logger && $logger('Выборка сообщений…');

        $ids = $this->samplePostIds((int) $run->sample_size);
        $note = count($ids) < (int) $run->sample_size
            ? 'Уникальных авторов с текстом меньше '.$run->sample_size.'. В выборке '.count($ids).'.'
            : null;

        $run->update([
            'sampled_count' => count($ids),
            'note' => $note,
        ]);
        $run->sampled_count = count($ids);

        if ($ids === []) {
            $run->update(['note' => 'В базе нет текстовых сообщений Telegram.']);

            return;
        }

        if ($this->cancelRequested($run->id)) {
            return;
        }

        $promptBody = trim((string) $run->prompt_body);
        if ($promptBody === '') {
            throw new RuntimeException('В прогоне нет текста системного промпта.');
        }

        $specialities = (new Dictionary)->getAll(DictionaryEnum::Speciality, ApiDataTypeEnum::Builder);
        $prompt = BuilderAiPromptInjector::injectSpecialitiesList($promptBody, $specialities);

        $ai = new ApiAIOllama;
        $ai->setConfig(is_array($run->provider_options) ? $run->provider_options : []);
        $classifier = app(BuilderServiceOfferClassifier::class);

        $logger && $logger(sprintf('Модель %s, сообщений %d.', (string) $run->model, count($ids)));
        Log::info('[parsing-accuracy-test] старт обработки', [
            'run_id' => $run->id,
            'sampled' => count($ids),
            'model' => $run->model,
            'prompt' => $run->prompt_label,
        ]);

        foreach ($ids as $index => $postId) {
            if ($this->cancelRequested($run->id)) {
                return;
            }

            try {
                $this->processPost($run, $ai, $classifier, $prompt, $postId);
            } catch (AiProviderUnavailableException $e) {
                throw $e;
            } catch (Throwable $e) {
                $run->error_count++;
                $run->processed_count++;
                $run->last_error = mb_substr($e->getMessage(), 0, 2000);
                $this->persistProgress($run);
                $logger && $logger('Ошибка поста '.$postId.': '.$e->getMessage());
            }

            if (($index + 1) % 25 === 0) {
                $logger && $logger(sprintf(
                    'Обработано %d/%d, целевых %d.',
                    $run->processed_count,
                    $run->sampled_count,
                    $run->target_count
                ));
            }
        }
    }

    private function processPost(
        ParsingTestRun $run,
        ApiAIOllama $ai,
        BuilderServiceOfferClassifier $classifier,
        string $prompt,
        int $postId,
    ): void {
        $post = ApiChannelPost::query()->with(['channel', 'apiPostUser'])->find($postId);
        if ($post === null) {
            $run->error_count++;
            $run->processed_count++;
            $run->last_error = 'Сообщение '.$postId.' не найдено.';
            $this->persistProgress($run);

            return;
        }

        $text = trim((string) $post->post);
        $pass1 = $classifier->classify($ai, $text);
        $run->processed_count++;

        if ($pass1['type'] !== BuilderServiceOfferClassifier::TYPE_SERVICE) {
            $this->persistProgress($run);

            return;
        }

        $ai->setGenerationOptions();
        $ai->setPromt($prompt);
        $ai->setText($text);

        try {
            $result = $ai->getResult(ApiDataTypeEnum::Builder);
        } catch (AiProviderUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 2000);
            $run->target_count++;
            $run->extraction_error_count++;
            $run->last_error = $message;
            $this->storeTarget(
                $run,
                $post,
                BuilderServiceOfferClassifier::TYPE_SERVICE,
                ['json' => ['ai_reason' => $pass1['reason']]],
                $message,
            );
            $this->persistProgress($run);

            return;
        }

        $rawType = $result['json']['ai_type'] ?? '';
        $decision = BuilderTargetDecision::fromModelType(
            is_scalar($rawType) ? (string) $rawType : '',
            $text,
        );

        if ($decision['overridden_to_vacancy']) {
            $run->heuristic_reject_count++;
        }
        if ($decision['is_target']) {
            $run->target_count++;
            $this->storeTarget($run, $post, $decision['ai_type'], $result);
        }
        $this->persistProgress($run);
    }

    /**
     * @param  array{origin?: mixed, json?: array<string, mixed>}  $result
     */
    private function storeTarget(
        ParsingTestRun $run,
        ApiChannelPost $post,
        string $aiType,
        array $result,
        ?string $extractionError = null,
    ): void {
        $user = $post->apiPostUser;
        $name = trim(implode(' ', array_filter([
            $user?->first_name,
            $user?->last_name,
        ], static fn ($part): bool => is_string($part) && trim($part) !== '')));
        $login = $user?->username ?: $post->user_login;
        $json = is_array($result['json'] ?? null) ? $result['json'] : [];
        $reason = $json['ai_reason'] ?? null;

        ParsingTestMessage::query()->create([
            'parsing_test_run_id' => $run->id,
            'api_channel_post_id' => $post->id,
            'api_post_user_id' => $post->api_post_user_id,
            'source' => ApiChannelSourceEnum::Telegram->value,
            'author_name' => $name !== '' ? mb_substr($name, 0, 255) : null,
            'author_login' => is_string($login) && $login !== '' ? mb_substr($login, 0, 255) : null,
            'channel_title' => $post->channel?->title
                ? mb_substr((string) $post->channel->title, 0, 255)
                : null,
            'post_date' => $post->post_date,
            'post_text' => (string) $post->post,
            'ai_type' => mb_substr($aiType, 0, 64),
            'ai_reason' => is_scalar($reason) && trim((string) $reason) !== ''
                ? mb_substr(trim((string) $reason), 0, 5000)
                : null,
            'specialities' => $this->specialitiesText($json['service_types'] ?? null),
            'model_reply' => is_string($result['origin'] ?? null)
                ? mb_substr($result['origin'], 0, 20000)
                : null,
            'extraction_error' => $extractionError !== null && $extractionError !== ''
                ? mb_substr($extractionError, 0, 2000)
                : null,
        ]);
    }

    private function persistProgress(ParsingTestRun $run): void
    {
        ParsingTestRun::query()->whereKey($run->id)->update([
            'sampled_count' => $run->sampled_count,
            'processed_count' => $run->processed_count,
            'target_count' => $run->target_count,
            'heuristic_reject_count' => $run->heuristic_reject_count,
            'error_count' => $run->error_count,
            'extraction_error_count' => $run->extraction_error_count,
            'last_error' => $run->last_error,
            'note' => $run->note,
            'updated_at' => now(),
        ]);
    }

    private function finishRun(ParsingTestRun $run): void
    {
        if ($this->cancelRequested($run->id)) {
            $this->markCancelled($run);

            return;
        }

        $updated = ParsingTestRun::query()
            ->whereKey($run->id)
            ->where('status', ParsingTestRunStatus::Running->value)
            ->whereNull('cancel_requested_at')
            ->update([
                'status' => ParsingTestRunStatus::Completed->value,
                'finished_at' => now(),
                'pid' => null,
            ]);

        if ($updated !== 1 && $this->cancelRequested($run->id)) {
            $this->markCancelled($run);
        }
    }

    private function markCancelled(ParsingTestRun $run): void
    {
        ParsingTestRun::query()->whereKey($run->id)->update([
            'status' => ParsingTestRunStatus::Cancelled->value,
            'finished_at' => now(),
            'error_message' => 'Остановлен вручную',
            'pid' => null,
        ]);
    }

    private function cancelRequested(int $runId): bool
    {
        return ParsingTestRun::query()
            ->whereKey($runId)
            ->whereNotNull('cancel_requested_at')
            ->exists();
    }

    private function signalProcess(int $pid): void
    {
        if ($pid <= 0 || $pid === getmypid() || ! function_exists('posix_kill')) {
            return;
        }

        posix_kill($pid, SIGTERM);
    }

    private function pidIsGone(int $pid): bool
    {
        if ($pid <= 0 || ! function_exists('posix_kill')) {
            return false;
        }

        if (posix_kill($pid, 0)) {
            return false;
        }

        $error = function_exists('posix_get_last_error') ? posix_get_last_error() : 0;
        $noSuchProcess = defined('ESRCH') ? ESRCH : 3;

        return $error === $noSuchProcess;
    }

    private function specialitiesText(mixed $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item) && trim((string) $item) !== '') {
                $parts[] = trim((string) $item);
            }
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(', ', $parts), 0, 2000);
    }

    private function resolveOllama(): ApiAi
    {
        $ai = ApiAi::query()
            ->where('api_source', ApiAiSourceEnum::OllamaQwen)
            ->where('status', ApiAiStatusEnum::Active)
            ->whereHas('channels', static function ($query): void {
                $query->where('is_company', ApiDataTypeEnum::Builder);
            })
            ->orderBy('id')
            ->first();

        if ($ai === null) {
            $ai = ApiAi::query()
                ->where('api_source', ApiAiSourceEnum::OllamaQwen)
                ->where('status', ApiAiStatusEnum::Active)
                ->orderBy('id')
                ->first();
        }

        if ($ai === null) {
            throw new RuntimeException('Нет активного сервиса ИИ Ollama Qwen.');
        }

        return $ai;
    }

    /**
     * Случайные разные авторы. RAND() снаружи DISTINCT, иначе MySQL с ONLY_FULL_GROUP_BY
     * отвергает сортировку по функции, которой нет в списке выборки.
     *
     * @return list<int|string>
     */
    private function randomDistinctValues(string $column, int $limit, bool $withUserId): array
    {
        if (! in_array($column, ['api_post_user_id', 'user_login'], true) || $limit < 1) {
            return [];
        }

        $query = $this->telegramPostsQuery()->select('api_channel_posts.'.$column);
        if ($withUserId) {
            $query->whereNotNull('api_channel_posts.api_post_user_id');
        } else {
            $query->whereNull('api_channel_posts.api_post_user_id')
                ->whereNotNull('api_channel_posts.user_login')
                ->where('api_channel_posts.user_login', '!=', '');
        }

        return DB::query()
            ->fromSub($query->distinct(), 'authors')
            ->inRandomOrder()
            ->limit($limit)
            ->pluck($column)
            ->all();
    }

    private function telegramPostsQuery()
    {
        return ApiChannelPost::query()
            ->join(ApiChannel::table(), 'api_channels.id', '=', 'api_channel_posts.api_channel_id')
            ->where('api_channels.channel_source', ApiChannelSourceEnum::Telegram->value)
            ->whereNotNull('api_channel_posts.post')
            ->whereRaw('LENGTH(TRIM(api_channel_posts.post)) > 0');
    }

    private function spawn(ParsingTestRun $run): void
    {
        $php = $this->resolvePhpCliBinary();
        $this->appendLog('Кнопка запросила прогон '.$run->id.', php: '.$php);

        try {
            $process = $this->backgroundProcess($run->id, $php);
            $process->setTimeout(20);
            $process->run();
            if (! $process->isSuccessful()) {
                $output = trim($process->getErrorOutput()."\n".$process->getOutput());
                throw new RuntimeException($output !== '' ? $output : 'Команда запуска вернула ошибку.');
            }
        } catch (Throwable $e) {
            $this->appendLog('Веб-запрос не стартовал процесс: '.$e->getMessage());
            $run->update([
                'note' => 'Сайт не открыл фоновый процесс. Планировщик подхватит тест в течение минуты.',
            ]);
            Log::warning('[parsing-accuracy-test] не удалось стартовать процесс из веб-запроса', [
                'run_id' => $run->id,
                'php' => $php,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function backgroundProcess(int $runId, string $php): Process
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $script = sprintf(
                'Start-Process -WindowStyle Hidden -FilePath %s -WorkingDirectory %s -ArgumentList %s,%s,%s',
                $this->powershellQuote($php),
                $this->powershellQuote(base_path()),
                $this->powershellQuote(base_path('artisan')),
                $this->powershellQuote('app:parsing-accuracy-test'),
                $this->powershellQuote((string) $runId),
            );

            return new Process([
                'powershell',
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                $script,
            ], base_path());
        }

        $log = $this->logPath();
        $inner = sprintf(
            '%s %s app:parsing-accuracy-test %d >> %s 2>&1',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            $runId,
            escapeshellarg($log),
        );
        $script = 'cd '.escapeshellarg(base_path()).' && setsid '.$inner.' < /dev/null &';

        return new Process(['sh', '-c', $script], base_path());
    }

    private function resolvePhpCliBinary(): string
    {
        $candidates = [];
        if (PHP_BINARY !== '' && ! preg_match('/fpm|cgi|lsphp/i', PHP_BINARY)) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        if (PHP_BINDIR !== '') {
            $candidates[] = PHP_BINDIR.'/php';
        }

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    private function logPath(): string
    {
        return storage_path('logs/parsing-accuracy-test.log');
    }

    private function appendLog(string $line): void
    {
        file_put_contents(
            $this->logPath(),
            '['.now()->format('Y-m-d H:i:s').'] '.$line.PHP_EOL,
            FILE_APPEND
        );
    }

    private function logTail(): string
    {
        $path = $this->logPath();
        if (! is_file($path)) {
            return '';
        }

        $size = filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $read = (int) min($size, 1500);
        fseek($handle, -$read, SEEK_END);
        $text = trim((string) stream_get_contents($handle));
        fclose($handle);

        return $text === '' ? '' : mb_substr($text, -1500);
    }

    private function powershellQuote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
