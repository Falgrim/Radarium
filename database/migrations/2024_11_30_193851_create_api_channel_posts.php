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
        Schema::create('api_channel_posts', function (Blueprint $table) {
            $table->id();
            $table->integer('api_channel_id');
            $table->string('user_login');
            $table->string('post_id');
            $table->dateTime('post_date');
            $table->text('post');
            $table->smallInteger('ai_parse_status')->default(0);
            $table->json('ai_result')->nullable();
            $table->dateTime('ai_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_channel_posts');
    }
};
