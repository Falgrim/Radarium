<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enum\DictionaryEnum;
use App\Models\Builder;
use App\Services\AuthorCatalogSpecialitiesSync;
use App\Services\BuilderSpecialityMatcher;
use App\Services\Dictionary;
use Illuminate\Console\Command;

class RematchBuilderSpecialities extends Command
{
    protected $signature = 'app:builders:rematch_specialities
                            {--dry-run : Показать изменения без записи в БД}
                            {--chunk=500 : Размер чанка lazyById}
                            {--only-empty : Только builders без связей specialities}
                            {--builder= : Только указанный id builder}';

    protected $description = 'Пересчитать специализации builders по тексту поста и (если есть) сохранённому JSON ответа ИИ';

    public function handle(Dictionary $dictionary, BuilderSpecialityMatcher $matcher): int
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
        $changed = 0;
        $idsSum = 0;
        $touchedUserIds = [];

        foreach ($query->lazyById($chunk) as $builder) {
            ++$processed;
            $post = $builder->post;
            if ($post === null) {
                $this->warn("Builder {$builder->id}: нет поста, пропуск");
                continue;
            }

            $text = (string) ($post->post ?? '');
            $aiList = $this->extractAiSpecialitiesFromPost($post->ai_result);

            $resolved = $matcher->resolve($text, $aiList, AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR);
            $newIds = $resolved['ids'];
            sort($newIds);

            $oldIds = $builder->specialities()->pluck('dictionary_speciality_id')->map('intval')->sort()->values()->all();

            if ($oldIds === $newIds) {
                continue;
            }

            ++$changed;
            $idsSum += count($newIds);

            if ($dryRun) {
                $this->line("Builder {$builder->id}: было [".implode(',', $oldIds).'] → станет ['.implode(',', $newIds).']');
                continue;
            }

            $dictionary->updateRelations(DictionaryEnum::Speciality, 'builder', (int) $builder->id, $newIds);
            $touchedUserIds[] = (int) $builder->api_post_user_id;
        }

        if (! $dryRun && $touchedUserIds !== []) {
            $sync = app(AuthorCatalogSpecialitiesSync::class);
            foreach (array_values(array_unique($touchedUserIds)) as $userId) {
                $sync->syncBuildersForUser($userId, AuthorCatalogSpecialitiesSync::DEFAULT_MAX_SPECIALITIES_PER_AUTHOR, false);
            }
        }

        $this->info("Обработано: {$processed}, изменено: {$changed}".($dryRun ? ' (dry-run)' : ''));
        if (! $dryRun && $changed > 0) {
            $this->info('Среднее число специализаций на изменённую запись: '.round($idsSum / $changed, 2));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    private function extractAiSpecialitiesFromPost(mixed $aiResult): ?array
    {
        if (! is_string($aiResult) || $aiResult === '') {
            return null;
        }

        $decoded = json_decode($aiResult, true);
        if (! is_array($decoded)) {
            return null;
        }

        $out = [];
        foreach (['specialities', 'service_types'] as $key) {
            if (! isset($decoded[$key]) || ! is_array($decoded[$key])) {
                continue;
            }
            foreach ($decoded[$key] as $v) {
                if (is_string($v) && $v !== '') {
                    $out[] = $v;
                }
            }
        }

        return $out === [] ? null : array_values(array_unique($out));
    }
}
