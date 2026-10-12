<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\AuthorSpecialityProfiler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportAuthorSpecialitiesGoldset extends Command
{
    protected $signature = 'app:builders:author_specialities_export
                            {--out=storage/app/author_specialities_goldset.csv : Путь CSV}
                            {--limit=200 : Сколько авторов выгрузить}
                            {--dry-run : Не писать файл, только статистика страт}';

    protected $description = 'Экспорт стратифицированной выборки авторов для ручной разметки специализаций (золотой набор)';

    public function handle(AuthorSpecialityProfiler $profiler): int
    {
        $limit = max(10, (int) $this->option('limit'));
        $out = (string) $this->option('out');
        $dryRun = (bool) $this->option('dry-run');

        $rows = Builder::query()
            ->select('api_post_user_id', DB::raw('COUNT(*) as post_cnt'))
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->groupBy('api_post_user_id')
            ->get();

        $buckets = [
            '1' => [],
            '2-5' => [],
            '6+' => [],
        ];
        foreach ($rows as $row) {
            $uid = (int) $row->api_post_user_id;
            $c = (int) $row->post_cnt;
            if ($c <= 1) {
                $buckets['1'][] = $uid;
            } elseif ($c <= 5) {
                $buckets['2-5'][] = $uid;
            } else {
                $buckets['6+'][] = $uid;
            }
        }

        foreach ($buckets as $name => $ids) {
            $this->info("Страта {$name}: ".count($ids));
        }

        $per = max(1, (int) floor($limit / 3));
        $selected = [];
        foreach ($buckets as $ids) {
            shuffle($ids);
            $selected = array_merge($selected, array_slice($ids, 0, $per));
        }
        $selected = array_values(array_unique(array_slice($selected, 0, $limit)));

        if ($dryRun) {
            $this->info('Выбрано для экспорта: '.count($selected).' (dry-run)');

            return self::SUCCESS;
        }

        $dir = dirname($out);
        if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($out, 'wb');
        if ($fh === false) {
            $this->error("Не удалось открыть {$out}");

            return self::FAILURE;
        }

        fputcsv($fh, [
            'api_post_user_id',
            'post_count',
            'current_speciality_ids',
            'current_speciality_titles',
            'proposed_speciality_ids',
            'proposed_speciality_titles',
            'proposed_scores',
            'post_snippets',
            'gold_speciality_ids',
            'notes',
        ], ';');

        foreach ($selected as $userId) {
            $builders = Builder::query()
                ->where('api_post_user_id', $userId)
                ->where('status', ApiPostAiStatusEnum::Active)
                ->whereHas('post', function ($q): void {
                    $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
                })
                ->with(['post', 'specialities.dictionarySpeciality'])
                ->orderByDesc('id')
                ->get();

            $currentIds = [];
            $currentTitles = [];
            foreach ($builders as $b) {
                foreach ($b->specialities as $bs) {
                    $id = (int) $bs->dictionary_speciality_id;
                    if (in_array($id, $currentIds, true)) {
                        continue;
                    }
                    $currentIds[] = $id;
                    $currentTitles[] = (string) ($bs->dictionarySpeciality->title ?? $id);
                }
            }

            $profile = $profiler->profileBuildersForUser(
                $userId,
                AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR,
                true,
                false
            );

            $proposedIds = $profile['top_speciality_ids'];
            $proposedTitles = [];
            $proposedScores = [];
            foreach ($profile['scores'] as $s) {
                if (! ($s['is_selected'] ?? false)) {
                    continue;
                }
                $proposedScores[] = $s['dictionary_speciality_id'].':'.$s['score'];
            }
            $titleMap = \App\Models\DictionarySpeciality::query()
                ->whereIn('id', $proposedIds)
                ->pluck('title', 'id');
            foreach ($proposedIds as $pid) {
                $proposedTitles[] = (string) ($titleMap[$pid] ?? $pid);
            }

            $snippets = [];
            foreach ($builders->take(3) as $b) {
                $t = (string) ($b->post->post ?? '');
                $snippets[] = mb_substr(preg_replace('/\s+/u', ' ', $t) ?? $t, 0, 180);
            }

            fputcsv($fh, [
                $userId,
                $builders->count(),
                implode(',', $currentIds),
                implode(' | ', $currentTitles),
                implode(',', $proposedIds),
                implode(' | ', $proposedTitles),
                implode(',', $proposedScores),
                implode(' || ', $snippets),
                '',
                '',
            ], ';');
        }

        fclose($fh);
        $this->info("Записано авторов: ".count($selected)." → {$out}");

        return self::SUCCESS;
    }
}
