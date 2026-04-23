<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('builder_specialities')) {
            return;
        }

        if (! Schema::hasColumn('builder_specialities', 'source')) {
            Schema::table('builder_specialities', function (Blueprint $table): void {
                $table->string('source', 16)->default('auto')->after('dictionary_speciality_id');
            });
        }

        DB::table('builder_specialities')->whereNull('source')->update(['source' => 'auto']);

        $indexName = 'builder_specialities_builder_id_source_idx';
        $existingIndexes = collect(Schema::getIndexes('builder_specialities'))->pluck('name')->all();
        if (! in_array($indexName, $existingIndexes, true)) {
            Schema::table('builder_specialities', function (Blueprint $table) use ($indexName): void {
                $table->index(['builder_id', 'source'], $indexName);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('builder_specialities')) {
            return;
        }

        $indexName = 'builder_specialities_builder_id_source_idx';
        $existingIndexes = collect(Schema::getIndexes('builder_specialities'))->pluck('name')->all();
        if (in_array($indexName, $existingIndexes, true)) {
            Schema::table('builder_specialities', function (Blueprint $table) use ($indexName): void {
                $table->dropIndex($indexName);
            });
        }

        if (Schema::hasColumn('builder_specialities', 'source')) {
            Schema::table('builder_specialities', function (Blueprint $table): void {
                $table->dropColumn('source');
            });
        }
    }
};
