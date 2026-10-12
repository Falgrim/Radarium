<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\BuilderPostSpecialityEvidenceWriter;
use Illuminate\Console\Command;

class BackfillBuilderSpecialityEvidence extends Command
{
    protected $signature = 'app:builders:backfill_speciality_evidence
                            {--dry-run : Только посчитать, без записи}
                            {--chunk=200 : Размер чанка}
                            {--force : Перезаписать evidence даже если уже есть}';

    protected $description = 'Backfill builder_post_speciality_evidence по Complete-постам активных builders';

    public function handle(BuilderPostSpecialityEvidenceWriter $writer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');
        $version = (string) config('author_speciality_profile.matcher_version', 'v2');

        $query = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with(['post']);

        $processed = 0;
        $written = 0;
        $skipped = 0;

        foreach ($query->lazyById($chunk) as $builder) {
            $post = $builder->post;
            if (! $post) {
                continue;
            }
            ++$processed;

            if (! $force) {
                $exists = \App\Models\BuilderPostSpecialityEvidence::query()
                    ->where('api_channel_post_id', (int) $post->id)
                    ->where('matcher_version', $version)
                    ->exists();
                if ($exists) {
                    ++$skipped;
                    continue;
                }
            }

            $ai = BuilderPostSpecialityEvidenceWriter::extractAiSpecialitiesFromPostJson($post->ai_result);
            if ($dryRun) {
                $collected = app(\App\Services\BuilderSpecialityMatcher::class)
                    ->collectEvidence((string) ($post->post ?? ''), $ai === [] ? null : $ai);
                $written += count($collected['by_id']);
                continue;
            }

            $r = $writer->writeForPost(
                (int) $post->id,
                (string) ($post->post ?? ''),
                $ai === [] ? null : $ai,
                $version
            );
            $written += $r['written'];
        }

        $this->info(
            "Постов: {$processed}, evidence строк: {$written}, пропущено (уже есть): {$skipped}"
            .($dryRun ? ' (dry-run)' : '')
        );

        return self::SUCCESS;
    }
}
