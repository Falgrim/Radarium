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
        Schema::create('api_ai_lists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description');
            $table->string('api_source')->comment('Тип АПИ сервиса, например: yandexgpt4');
            $table->json('options');
            $table->smallInteger('status')->default(1);
            $table->integer('balance_sum')->default(0)->comment('Баланс в копейках (умноженный на 100)');
            $table->dateTime('date_balance')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_bot_lists');
    }
};
