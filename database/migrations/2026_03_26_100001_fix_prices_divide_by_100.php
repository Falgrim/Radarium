<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Исправление бага: цены ошибочно сохранялись с множителем *100.
 * Делим все ненулевые значения на 100, возвращая реальные рубли.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE builders SET price_by_hour = price_by_hour / 100 WHERE price_by_hour IS NOT NULL AND price_by_hour != 0');
        DB::statement('UPDATE builders SET price_by_project = price_by_project / 100 WHERE price_by_project IS NOT NULL AND price_by_project != 0');
        DB::statement('UPDATE builders SET price_by_month = price_by_month / 100 WHERE price_by_month IS NOT NULL AND price_by_month != 0');

        DB::statement('UPDATE specialists SET price_by_hour = price_by_hour / 100 WHERE price_by_hour IS NOT NULL AND price_by_hour != 0');
        DB::statement('UPDATE specialists SET price_by_project = price_by_project / 100 WHERE price_by_project IS NOT NULL AND price_by_project != 0');
        DB::statement('UPDATE specialists SET price_by_month = price_by_month / 100 WHERE price_by_month IS NOT NULL AND price_by_month != 0');

        DB::statement('UPDATE company_jobs SET min_price = min_price / 100 WHERE min_price IS NOT NULL AND min_price != 0');
        DB::statement('UPDATE company_jobs SET max_price = max_price / 100 WHERE max_price IS NOT NULL AND max_price != 0');
    }

    public function down(): void
    {
        DB::statement('UPDATE builders SET price_by_hour = price_by_hour * 100 WHERE price_by_hour IS NOT NULL AND price_by_hour != 0');
        DB::statement('UPDATE builders SET price_by_project = price_by_project * 100 WHERE price_by_project IS NOT NULL AND price_by_project != 0');
        DB::statement('UPDATE builders SET price_by_month = price_by_month * 100 WHERE price_by_month IS NOT NULL AND price_by_month != 0');

        DB::statement('UPDATE specialists SET price_by_hour = price_by_hour * 100 WHERE price_by_hour IS NOT NULL AND price_by_hour != 0');
        DB::statement('UPDATE specialists SET price_by_project = price_by_project * 100 WHERE price_by_project IS NOT NULL AND price_by_project != 0');
        DB::statement('UPDATE specialists SET price_by_month = price_by_month * 100 WHERE price_by_month IS NOT NULL AND price_by_month != 0');

        DB::statement('UPDATE company_jobs SET min_price = min_price * 100 WHERE min_price IS NOT NULL AND min_price != 0');
        DB::statement('UPDATE company_jobs SET max_price = max_price * 100 WHERE max_price IS NOT NULL AND max_price != 0');
    }
};
