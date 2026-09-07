<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Разбор опции-даты в командах эксплуатации.
 *
 * Дата принимается только полным днём в формате Y-m-d: границу выборки должно быть видно
 * в самой команде, иначе оператор не понимает, какой период он затрагивает.
 */
trait ParsesDateOption
{
    /**
     * Возвращает null и печатает ошибку, если значение опции — не дата Y-m-d.
     */
    protected function parseDateOption(string $optionName, string $raw): ?CarbonImmutable
    {
        $raw = trim($raw);

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $raw);
        } catch (Throwable) {
            $date = null;
        }

        // Carbon переполняет невозможные даты («2026-13-45» → следующий год), поэтому сверяем обратное представление.
        if ($date === null || $date->format('Y-m-d') !== $raw) {
            $this->error('Параметр --'.$optionName.' должен быть датой в формате Y-m-d, получено: '.$raw);

            return null;
        }

        return $date;
    }
}
