<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Индексы под выборку последних постов и фильтры каталога /specialists.
     */
    public function up(): void
    {
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->index(
                ['api_post_user_id', 'ai_parse_status', 'post_date'],
                'acp_user_ai_status_postdate_idx'
            );
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->index(['api_post_user_id', 'status'], 'sp_user_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->dropIndex('acp_user_ai_status_postdate_idx');
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->dropIndex('sp_user_status_idx');
        });
    }
};
