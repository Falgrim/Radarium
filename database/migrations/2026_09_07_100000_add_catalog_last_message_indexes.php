<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Индексы под сортировку каталогов по дате последнего сообщения активной карточки:
     * выборка активных карточек автора и переход от карточки к её посту.
     */
    public function up(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->index(['api_post_user_id', 'status'], 'bl_user_status_idx');
            $table->index('api_channel_post_id', 'bl_post_id_idx');
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->index('api_channel_post_id', 'sp_post_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->dropIndex('bl_user_status_idx');
            $table->dropIndex('bl_post_id_idx');
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->dropIndex('sp_post_id_idx');
        });
    }
};
