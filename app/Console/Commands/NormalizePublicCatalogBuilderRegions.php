<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Builder;
use App\Services\RussianRegionNormalizer;
use App\Support\PublicBuilderCatalogScope;
use Illuminate\Console\Command;

/**
 * Пересчёт поля region только у карточек, попадающих в публичный каталог строителей
 * (тот же scope, что и у {@see \App\Http\Controllers\BuilderController}).
 */
class NormalizePublicCatalogBuilderRegions extends Command
{
    protected $signature = 'regions:normalize-public-catalog-builders
                            {--dry-run : Показать пары старое → новое без записи в БД}
                            {--chunk=200 : Размер чанка}';

    protected $description = 'Нормализовать region у строителей в публичном каталоге (канон из справочника или NULL)';

    public function handle(RussianRegionNormalizer $normalizer): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $base = PublicBuilderCatalogScope::buildersMatchingCatalogScope([], [], true);

        $toCanonical = 0;
        $cleared = 0;
        $unchanged = 0;

        (clone $base)->orderBy('id')->chunkById($chunk, function ($rows) use ($normalizer, $dry, &$toCanonical, &$cleared, &$unchanged): void {
            foreach ($rows as $builder) {
                $raw = $builder->region;
                $canonical = null;
                if ($raw !== null && trim((string) $raw) !== '') {
                    $canonical = $normalizer->normalize(trim((string) $raw));
                }

                $currentNorm = $raw === null ? null : trim((string) $raw);
                if ($currentNorm === '') {
                    $currentNorm = null;
                }

                if ($canonical === $currentNorm) {
                    $unchanged++;

                    continue;
                }

                if ($canonical === null) {
                    $this->line("builder #{$builder->id}: «{$raw}» → NULL");
                    $cleared++;
                } else {
                    $this->line("builder #{$builder->id}: «{$raw}» → «{$canonical}»");
                    $toCanonical++;
                }

                if (! $dry) {
                    Builder::whereKey($builder->id)->update(['region' => $canonical]);
                }
            }
        });

        $this->info(sprintf(
            'Готово. Приведено к канону: %d, обнулено (не в справочнике): %d, без изменений: %d%s',
            $toCanonical,
            $cleared,
            $unchanged,
            $dry ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
