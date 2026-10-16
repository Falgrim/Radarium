<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Models\DictionarySpeciality;
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\AuthorSpecialityProfiler;
use App\Services\BuilderPostSpecialityEvidenceWriter;
use App\Services\BuilderSpecialityMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExportAuthorSpecialitiesGoldset extends Command
{
    protected $signature = 'app:builders:author_specialities_export
                            {--out=storage/app/author_specialities_goldset.csv : Путь CSV}
                            {--limit=200 : Сколько авторов выгрузить (для --lost-only — только если флаг --limit передан явно)}
                            {--lost-only : Только авторы с непустым текущим pivot и пустым новым профилем}
                            {--per-post : 1 строка = 1 сообщение (полный текст + диагностика)}
                            {--dry-run : Не писать файл, только статистика}';

    protected $description = 'Экспорт авторов builders для разметки специализаций (золотой набор или диагностика lost)';

    public function handle(AuthorSpecialityProfiler $profiler, BuilderSpecialityMatcher $matcher): int
    {
        $out = (string) $this->option('out');
        $dryRun = (bool) $this->option('dry-run');
        $lostOnly = (bool) $this->option('lost-only');
        $perPost = (bool) $this->option('per-post');
        $limitExplicit = $this->input->hasParameterOption('--limit', true);
        $limit = max(1, (int) $this->option('limit'));

        if ($lostOnly) {
            $selected = $this->selectLostAuthors($profiler);
            $this->info('Авторов с обнулившейся специализацией: '.count($selected));
            if ($limitExplicit) {
                $selected = array_slice($selected, 0, $limit);
                $this->info("Применён --limit={$limit}: выбрано ".count($selected));
            }
        } else {
            $selected = $this->selectStratifiedAuthors($limit);
        }

        if ($dryRun) {
            $this->info('Выбрано для экспорта: '.count($selected).' (dry-run)');

            return self::SUCCESS;
        }

        if ($selected === []) {
            $this->warn('Нет авторов для экспорта');

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

        /** @var array<int, string> $titleById */
        $titleById = DictionarySpeciality::query()
            ->pluck('title', 'id')
            ->mapWithKeys(static fn ($title, $id): array => [(int) $id => (string) $title])
            ->all();

        if ($perPost) {
            $written = $this->writePerPostCsv($fh, $selected, $profiler, $matcher, $titleById, $lostOnly);
            fclose($fh);
            $this->info("Записано строк (постов): {$written} → {$out}");
        } else {
            $written = $this->writePerAuthorCsv($fh, $selected, $profiler, $titleById);
            fclose($fh);
            $this->info("Записано авторов: {$written} → {$out}");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function selectStratifiedAuthors(int $limit): array
    {
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

        return array_values(array_unique(array_slice($selected, 0, $limit)));
    }

    /**
     * Авторы с непустым текущим pivot и пустым новым профилем.
     *
     * @return list<int>
     */
    private function selectLostAuthors(AuthorSpecialityProfiler $profiler): array
    {
        $userIds = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->distinct()
            ->pluck('api_post_user_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->sort()
            ->values()
            ->all();

        $lost = [];
        $noEvidence = 0;
        $belowThreshold = 0;

        foreach ($userIds as $userId) {
            $builders = $this->activeBuildersForUser($userId);
            $currentIds = $this->currentSpecialityIds($builders);
            if ($currentIds === []) {
                continue;
            }

            $profile = $profiler->profileBuildersForUser(
                $userId,
                AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR,
                true,
                false
            );
            if ($profile['top_speciality_ids'] !== []) {
                continue;
            }

            $lost[] = $userId;
            if ($profile['scores'] === []) {
                ++$noEvidence;
            } else {
                ++$belowThreshold;
            }
        }

        $this->info("  no_leaf_evidence: {$noEvidence}");
        $this->info("  below_threshold: {$belowThreshold}");

        return $lost;
    }

    /**
     * @param  resource  $fh
     * @param  list<int>  $selected
     * @param  array<int, string>  $titleById
     */
    private function writePerAuthorCsv($fh, array $selected, AuthorSpecialityProfiler $profiler, array $titleById): int
    {
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

        $written = 0;
        foreach ($selected as $userId) {
            $builders = $this->activeBuildersForUser($userId);
            $currentIds = $this->currentSpecialityIds($builders);
            $currentTitles = array_map(
                static fn (int $id): string => $titleById[$id] ?? (string) $id,
                $currentIds
            );

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
                $sid = (int) $s['dictionary_speciality_id'];
                $proposedScores[] = ($titleById[$sid] ?? (string) $sid).':'.$s['score'];
            }
            foreach ($proposedIds as $pid) {
                $proposedTitles[] = $titleById[$pid] ?? (string) $pid;
            }

            $snippets = [];
            foreach ($builders->take(3) as $b) {
                $t = (string) ($b->post->post ?? '');
                $snippets[] = mb_substr($this->normalizeWhitespace($t), 0, 180);
            }

            fputcsv($fh, [
                $userId,
                $builders->count(),
                implode(',', $currentIds),
                implode(' | ', $currentTitles),
                implode(',', $proposedIds),
                implode(' | ', $proposedTitles),
                implode(' | ', $proposedScores),
                implode(' || ', $snippets),
                '',
                '',
            ], ';');
            ++$written;
        }

        return $written;
    }

    /**
     * @param  resource  $fh
     * @param  list<int>  $selected
     * @param  array<int, string>  $titleById
     */
    private function writePerPostCsv(
        $fh,
        array $selected,
        AuthorSpecialityProfiler $profiler,
        BuilderSpecialityMatcher $matcher,
        array $titleById,
        bool $lostOnly,
    ): int {
        fputcsv($fh, [
            'api_post_user_id',
            'author_post_count',
            'empty_reason',
            'current_speciality_titles',
            'top_candidate_scores',
            'builder_id',
            'post_id',
            'post_date',
            'ai_specialities_raw',
            'post_evidence',
            'post_text',
        ], ';');

        $written = 0;
        foreach ($selected as $userId) {
            $builders = $this->activeBuildersForUser($userId);
            $currentIds = $this->currentSpecialityIds($builders);
            $currentTitles = array_map(
                static fn (int $id): string => $titleById[$id] ?? (string) $id,
                $currentIds
            );

            $profile = $profiler->profileBuildersForUser(
                $userId,
                AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR,
                true,
                false
            );

            $emptyReason = '';
            if ($lostOnly || $profile['top_speciality_ids'] === []) {
                if ($currentIds !== [] && $profile['top_speciality_ids'] === []) {
                    $emptyReason = $profile['scores'] === [] ? 'no_leaf_evidence' : 'below_threshold';
                }
            }

            $topCandidateScores = [];
            foreach (array_slice($profile['scores'], 0, 5) as $s) {
                $sid = (int) $s['dictionary_speciality_id'];
                $topCandidateScores[] = ($titleById[$sid] ?? (string) $sid).':'.$s['score'];
            }
            $topCandidateStr = implode(' | ', $topCandidateScores);
            $currentTitlesStr = implode(' | ', $currentTitles);
            $authorPostCount = $builders->count();

            foreach ($builders as $builder) {
                $post = $builder->post;
                if (! $post) {
                    continue;
                }

                $aiList = BuilderPostSpecialityEvidenceWriter::extractAiSpecialitiesFromPostJson($post->ai_result);
                $collected = $matcher->collectEvidence(
                    (string) ($post->post ?? ''),
                    $aiList === [] ? null : $aiList
                );

                $evidenceParts = [];
                foreach ($collected['by_id'] as $specId => $ev) {
                    $combined = (float) ($ev['combined'] ?? 0);
                    if ($combined <= 0) {
                        continue;
                    }
                    $sid = (int) $specId;
                    $evidenceParts[] = sprintf(
                        '%s:t=%.3f,a=%.3f,c=%.3f',
                        $titleById[$sid] ?? (string) $sid,
                        (float) ($ev['text_score'] ?? 0),
                        (float) ($ev['ai_score'] ?? 0),
                        $combined
                    );
                }
                // Сортировка по combined desc в строке
                usort($evidenceParts, static function (string $a, string $b): int {
                    preg_match('/c=([\d.]+)$/', $a, $ma);
                    preg_match('/c=([\d.]+)$/', $b, $mb);

                    return ((float) ($mb[1] ?? 0)) <=> ((float) ($ma[1] ?? 0));
                });

                $postDate = $post->post_date;
                $postDateStr = $postDate
                    ? (is_string($postDate) ? $postDate : $postDate->format('Y-m-d H:i:s'))
                    : '';

                fputcsv($fh, [
                    $userId,
                    $authorPostCount,
                    $emptyReason,
                    $currentTitlesStr,
                    $topCandidateStr,
                    (int) $builder->id,
                    (int) $post->id,
                    $postDateStr,
                    implode(' | ', $aiList),
                    implode(' || ', $evidenceParts),
                    $this->normalizeWhitespace((string) ($post->post ?? '')),
                ], ';');
                ++$written;
            }
        }

        return $written;
    }

    /**
     * @return Collection<int, Builder>
     */
    private function activeBuildersForUser(int $userId): Collection
    {
        return Builder::query()
            ->where('api_post_user_id', $userId)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with(['post', 'specialities.dictionarySpeciality'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Builder>  $builders
     * @return list<int>
     */
    private function currentSpecialityIds(Collection $builders): array
    {
        $currentIds = [];
        foreach ($builders as $b) {
            foreach ($b->specialities as $bs) {
                $id = (int) $bs->dictionary_speciality_id;
                if (in_array($id, $currentIds, true)) {
                    continue;
                }
                $currentIds[] = $id;
            }
        }

        return $currentIds;
    }

    private function normalizeWhitespace(string $text): string
    {
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }
}
