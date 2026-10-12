<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Models\BuilderPostSpecialityEvidence;
use App\Models\DictionarySpeciality;
use App\Services\BuilderSpecialityMatcher;
use Illuminate\Console\Command;

class BuilderSpecialityKeywordCandidates extends Command
{
    protected $signature = 'app:builders:speciality_keyword_candidates
                            {--out=storage/app/speciality_keyword_candidates.csv : CSV выход}
                            {--min-combined=0.55 : Мин. combined evidence для «уверенного» поста}
                            {--limit=3000 : Макс. постов}
                            {--top=30 : Кандидатов на специализацию}';

    protected $description = 'Кандидаты key_words по n-граммам из постов с высокой уверенностью evidence (PMI)';

    public function handle(BuilderSpecialityMatcher $matcher): int
    {
        $out = (string) $this->option('out');
        $minCombined = (float) $this->option('min-combined');
        $limit = max(100, (int) $this->option('limit'));
        $top = max(5, (int) $this->option('top'));
        $version = (string) config('author_speciality_profile.matcher_version', 'v2');

        $evidence = BuilderPostSpecialityEvidence::query()
            ->where('matcher_version', $version)
            ->where('combined', '>=', $minCombined)
            ->orderByDesc('combined')
            ->limit($limit)
            ->get();

        if ($evidence->isEmpty()) {
            $this->warn('Нет evidence — сначала app:builders:backfill_speciality_evidence');

            return self::SUCCESS;
        }

        $postIds = $evidence->pluck('api_channel_post_id')->unique()->all();
        $posts = Builder::query()
            ->whereIn('api_channel_post_id', $postIds)
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with('post')
            ->get()
            ->keyBy('api_channel_post_id');

        $df = [];
        $tf = []; // specId => term => count
        $docsPerSpec = [];
        $totalDocs = 0;

        foreach ($evidence->groupBy('api_channel_post_id') as $postId => $rows) {
            $builder = $posts->get($postId);
            if (! $builder || ! $builder->post) {
                continue;
            }
            $norm = $matcher->normalizeTextPublic((string) $builder->post->post);
            $terms = $this->extractNgrams($norm);
            if ($terms === []) {
                continue;
            }
            ++$totalDocs;
            $seenDoc = [];
            foreach ($terms as $term) {
                if (! isset($seenDoc[$term])) {
                    $df[$term] = ($df[$term] ?? 0) + 1;
                    $seenDoc[$term] = true;
                }
            }
            $best = $rows->sortByDesc('combined')->first();
            $specId = (int) $best->dictionary_speciality_id;
            $docsPerSpec[$specId] = ($docsPerSpec[$specId] ?? 0) + 1;
            foreach ($terms as $term) {
                $tf[$specId][$term] = ($tf[$specId][$term] ?? 0) + 1;
            }
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
        fputcsv($fh, ['dictionary_speciality_id', 'title', 'term', 'tf', 'df', 'pmi', 'docs_in_spec'], ';');

        $titles = DictionarySpeciality::query()->pluck('title', 'id');
        $existing = [];
        foreach (DictionarySpeciality::query()->get() as $row) {
            foreach (array_merge(
                is_array($row->key_words) ? $row->key_words : [],
                is_array($row->weak_key_words) ? $row->weak_key_words : []
            ) as $kw) {
                $existing[(int) $row->id][mb_strtolower(trim((string) $kw))] = true;
            }
        }

        foreach ($tf as $specId => $terms) {
            $scored = [];
            $nSpec = max(1, (int) ($docsPerSpec[$specId] ?? 1));
            foreach ($terms as $term => $cnt) {
                if (isset($existing[$specId][$term])) {
                    continue;
                }
                if (mb_strlen($term) < 4) {
                    continue;
                }
                $docFreq = max(1, (int) ($df[$term] ?? 1));
                // PMI ≈ log( (tf/nSpec) / (df/N) )
                $pmi = log((($cnt / $nSpec) + 1e-9) / (($docFreq / max(1, $totalDocs)) + 1e-9));
                if ($pmi < 0.8 || $cnt < 2) {
                    continue;
                }
                $scored[] = [$term, $cnt, $docFreq, $pmi, $nSpec];
            }
            usort($scored, static fn ($a, $b): int => $b[3] <=> $a[3]);
            foreach (array_slice($scored, 0, $top) as $row) {
                fputcsv($fh, [
                    $specId,
                    (string) ($titles[$specId] ?? ''),
                    $row[0],
                    $row[1],
                    $row[2],
                    round($row[3], 4),
                    $row[4],
                ], ';');
            }
        }

        fclose($fh);
        $this->info("Кандидаты → {$out} (доков: {$totalDocs})");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function extractNgrams(string $normalized): array
    {
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => mb_strlen($t) >= 3 && ! is_numeric($t)
        ));
        $out = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; ++$i) {
            $out[] = $tokens[$i];
            if ($i + 1 < $n) {
                $out[] = $tokens[$i].' '.$tokens[$i + 1];
            }
        }

        return array_values(array_unique($out));
    }
}
