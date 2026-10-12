<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictionary_specialities', function (Blueprint $table): void {
            if (! Schema::hasColumn('dictionary_specialities', 'weak_key_words')) {
                $table->json('weak_key_words')->nullable()->after('key_words');
            }
            if (! Schema::hasColumn('dictionary_specialities', 'negative_key_words')) {
                $table->json('negative_key_words')->nullable()->after('weak_key_words');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dictionary_specialities', function (Blueprint $table): void {
            if (Schema::hasColumn('dictionary_specialities', 'negative_key_words')) {
                $table->dropColumn('negative_key_words');
            }
            if (Schema::hasColumn('dictionary_specialities', 'weak_key_words')) {
                $table->dropColumn('weak_key_words');
            }
        });
    }
};
