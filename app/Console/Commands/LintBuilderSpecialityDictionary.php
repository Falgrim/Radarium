<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\ApiChannelPostStatusEnum;
use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Builder;
use App\Models\DictionarySpeciality;
use App\Services\BuilderSpecialityMatcher;
use Illuminate\Console\Command;

class LintBuilderSpecialityDictionary extends Command
{
    protected $signature = 'app:builders:specialities_lint_dictionary
                            {--sample=2000 : Сколько постов для оценки DF ключей}
                            {--df-threshold=0.12 : Доля постов с ключом → предложить weak}';

    protected $description = 'Линтер справочника специализаций builders: дубли ключей, общие слова, DF по корпусу, листы-дубли групп';

    public function handle(BuilderSpecialityMatcher $matcher): int
    {
        $dict = DictionarySpeciality::query()
            ->where('api_data_type_id', ApiDataTypeEnum::Builder)
            ->get();

        $issues = 0;

        // Дубли названий лист/группа
        $byTitle = $dict->groupBy(static fn ($r) => mb_strtolower(trim((string) $r->title)));
        foreach ($byTitle as $title => $group) {
            if ($group->count() < 2) {
                continue;
            }
            $hasParent = $group->contains(static fn ($r) => $r->parent_id === null);
            $hasChild = $group->contains(static fn ($r) => $r->parent_id !== null);
            if ($hasParent && $hasChild) {
                $this->warn("Лист-дубль группы: «{$title}» ids=[".$group->pluck('id')->implode(',').']');
                ++$issues;
            }
        }

        // Дубли ключей между листьями
        $leafIds = [];
        $childOf = [];
        foreach ($dict as $item) {
            if ($item->parent_id) {
                $childOf[(int) $item->parent_id][] = (int) $item->id;
            }
        }
        foreach ($dict as $item) {
            $id = (int) $item->id;
            if (! isset($childOf[$id]) || $childOf[$id] === []) {
                $leafIds[$id] = true;
            }
        }

        $kwOwners = [];
        foreach ($dict as $item) {
            $id = (int) $item->id;
            if (! isset($leafIds[$id])) {
                continue;
            }
            foreach (array_merge(
                is_array($item->key_words) ? $item->key_words : [],
                is_array($item->weak_key_words) ? $item->weak_key_words : []
            ) as $kw) {
                $norm = mb_strtolower(trim((string) $kw));
                if ($norm === '') {
                    continue;
                }
                $kwOwners[$norm][] = $id;
            }
        }
        foreach ($kwOwners as $kw => $owners) {
            $owners = array_values(array_unique($owners));
            if (count($owners) > 1) {
                $this->warn("Дубль ключа «{$kw}» у листьев: [".implode(',', $owners).']');
                ++$issues;
            }
        }

        // Короткие/общие
        $shortCommon = ['пол', 'дом', 'кран', 'лом', 'сети', 'окна', 'плита'];
        foreach ($dict as $item) {
            foreach (is_array($item->key_words) ? $item->key_words : [] as $kw) {
                $norm = mb_strtolower(trim((string) $kw));
                if (in_array($norm, $shortCommon, true)) {
                    $this->warn("Сильный ключ слишком общий у #{$item->id} «{$item->title}»: {$kw}");
                    ++$issues;
                }
            }
        }

        // DF по корпусу
        $sample = max(100, (int) $this->option('sample'));
        $dfThreshold = (float) $this->option('df-threshold');
        $posts = Builder::query()
            ->where('status', ApiPostAiStatusEnum::Active)
            ->whereHas('post', function ($q): void {
                $q->where('ai_parse_status', ApiChannelPostStatusEnum::Complete);
            })
            ->with('post')
            ->orderByDesc('id')
            ->limit($sample)
            ->get();

        $texts = [];
        foreach ($posts as $b) {
            if ($b->post && $b->post->post !== '') {
                $texts[] = $matcher->normalizeTextPublic((string) $b->post->post);
            }
        }
        $n = count($texts);
        if ($n > 0) {
            $this->info("Корпус для DF: {$n} постов");
            $checked = [];
            foreach ($kwOwners as $kw => $owners) {
                if (isset($checked[$kw]) || mb_strlen($kw) < 4) {
                    continue;
                }
                $checked[$kw] = true;
                $hit = 0;
                foreach ($texts as $t) {
                    if (str_contains($t, $kw)) {
                        ++$hit;
                    }
                }
                $df = $hit / $n;
                if ($df >= $dfThreshold) {
                    $this->warn(sprintf(
                        'Высокий DF=%.2f для «%s» (листья %s) — кандидат в weak_key_words',
                        $df,
                        $kw,
                        implode(',', $owners)
                    ));
                    ++$issues;
                }
            }
        }

        $this->info("Найдено замечаний: {$issues}");

        return self::SUCCESS;
    }
}
