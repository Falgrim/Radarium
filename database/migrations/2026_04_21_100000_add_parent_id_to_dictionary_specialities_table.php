<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictionary_specialities', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->index('parent_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('dictionary_specialities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dictionary_specialities', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
