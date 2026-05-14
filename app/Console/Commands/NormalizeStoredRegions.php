<?php

namespace App\Console\Commands;

use App\Models\Builder;
use App\Models\Specialist;
use App\Services\RussianRegionNormalizer;
use Illuminate\Console\Command;

class NormalizeStoredRegions extends Command
{
    protected $signature = 'app:regions:normalize_stored
                            {--dry-run : Только вывести пары старое → новое}
                            {--chunk=500 : Размер чанка}';

    protected $description = 'Нормализовать поле region у существующих builders и specialists по справочнику (только замена на канон). Для публичного каталога строителей с обнулением нераспознанного: regions:normalize-public-catalog-builders';

    public function handle(RussianRegionNormalizer $normalizer): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $updatedBuilders = 0;
        $updatedSpecialists = 0;

        Builder::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($normalizer, $dry, &$updatedBuilders): void {
                foreach ($rows as $row) {
                    $canonical = $normalizer->normalize($row->region);
                    if ($canonical === null || $canonical === $row->region) {
                        continue;
                    }
                    $this->line("builder #{$row->id}: {$row->region} → {$canonical}");
                    if (! $dry) {
                        Builder::whereKey($row->id)->update(['region' => $canonical]);
                    }
                    $updatedBuilders++;
                }
            });

        Specialist::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($normalizer, $dry, &$updatedSpecialists): void {
                foreach ($rows as $row) {
                    $canonical = $normalizer->normalize($row->region);
                    if ($canonical === null || $canonical === $row->region) {
                        continue;
                    }
                    $this->line("specialist #{$row->id}: {$row->region} → {$canonical}");
                    if (! $dry) {
                        Specialist::whereKey($row->id)->update(['region' => $canonical]);
                    }
                    $updatedSpecialists++;
                }
            });

        $this->info(sprintf(
            'Готово. Builders: %d, specialists: %d%s',
            $updatedBuilders,
            $updatedSpecialists,
            $dry ? ' (dry-run)' : ''
        ));

        return self::SUCCESS;
    }
}
