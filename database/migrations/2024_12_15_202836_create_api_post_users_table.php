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
        Schema::create('api_post_users', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('user_type')->nullable();
            $table->string('channel_source')->comment('Тип АПИ сервиса, например: yandexgpt4');
            $table->string('phone')->nullable();
            $table->dateTime('last_online_date')->nullable();
            $table->json('external_info')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_post_users');
    }
};
