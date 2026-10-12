<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Builder;
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\BuilderPostSpecialityEvidenceWriter;
use Illuminate\Console\Command;

class RematchBuilderSpecialities extends Command
{
    protected $signature = 'app:builders:rematch_specialities
                            {--dry-run : Показать изменения без записи в БД}
                            {--chunk=500 : Размер чанка lazyById}
                            {--only-empty : Только builders без связей specialities}
                            {--builder= : Только указанный id builder}';

    protected $description = 'Пересчитать evidence постов и профили специализаций авторов builders';

    public function handle(BuilderPostSpecialityEvidenceWriter $evidenceWriter, AuthorCatalogSpecialitiesSync $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $onlyEmpty = (bool) $this->option('only-empty');
        $oneBuilder = $this->option('builder');
        $oneBuilderId = $oneBuilder !== null && $oneBuilder !== '' ? (int) $oneBuilder : null;

        $query = Builder::query()->with(['post', 'specialities']);

        if ($oneBuilderId !== null) {
            $query->whereKey($oneBuilderId);
        }

        if ($onlyEmpty) {
            $query->whereDoesntHave('specialities');
        }

        $processed = 0;
        $evidenceWritten = 0;
        $touchedUserIds = [];

        foreach ($query->lazyById($chunk) as $builder) {
            ++$processed;
            $post = $builder->post;
            if ($post === null) {
                $this->warn("Builder {$builder->id}: нет поста, пропуск");
                continue;
            }

            $text = (string) ($post->post ?? '');
            $aiList = BuilderPostSpecialityEvidenceWriter::extractAiSpecialitiesFromPostJson($post->ai_result);

            if (! $dryRun) {
                $written = $evidenceWriter->writeForPost(
                    (int) $post->id,
                    $text,
                    $aiList === [] ? null : $aiList
                );
                $evidenceWritten += $written['written'];
            }

            $touchedUserIds[] = (int) $builder->api_post_user_id;
        }

        $touchedUserIds = array_values(array_unique(array_filter($touchedUserIds)));
        $changedAuthors = 0;

        foreach ($touchedUserIds as $userId) {
            $r = $sync->syncBuildersForUser(
                $userId,
                AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR,
                $dryRun
            );
            if ($r['changed']) {
                ++$changedAuthors;
                if ($dryRun) {
                    $this->line(
                        "api_post_user_id={$userId}: → [".implode(',', $r['top_speciality_ids']).']'
                    );
                }
            }
        }

        $this->info(
            "Обработано builders: {$processed}, evidence записей: {$evidenceWritten}, авторов с изменениями: {$changedAuthors}"
            .($dryRun ? ' (dry-run)' : '')
        );

        return self::SUCCESS;
    }
}
