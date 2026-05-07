<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moderation_alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('api_channel_post_id')->nullable()->after('table_row_id');
        });
    }

    public function down(): void
    {
        Schema::table('moderation_alerts', function (Blueprint $table) {
            $table->dropColumn('api_channel_post_id');
        });
    }
};
