<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->string('ai_provider_used')->nullable()->after('ai_date');
        });
    }

    public function down(): void
    {
        Schema::table('api_channel_posts', function (Blueprint $table) {
            $table->dropColumn('ai_provider_used');
        });
    }
};
