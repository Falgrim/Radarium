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
        Schema::table('api_channels', function (Blueprint $table) {
            $table->dateTime('last_date_check')->nullable()->after('last_post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_channels', function (Blueprint $table) {
            $table->dropColumn('last_date_check');
        });
    }
};
