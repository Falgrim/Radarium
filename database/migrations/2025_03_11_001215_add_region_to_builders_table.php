<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->string('region', 100)->nullable()->after('post_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
