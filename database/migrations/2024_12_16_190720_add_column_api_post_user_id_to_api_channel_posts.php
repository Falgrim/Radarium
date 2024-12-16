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
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->integer('api_post_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->dropColumn('api_post_user_id');
        });
    }
};
