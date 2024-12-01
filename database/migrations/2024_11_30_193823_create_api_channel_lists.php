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
        Schema::create('api_channel_lists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('link');
            $table->string('description');
            $table->text('ai_promt')->nullable();
            $table->smallInteger('api_bot_list_id')->default(0);
            $table->string('api_source')->comment('Тип АПИ сервиса, например: telegram, vk')->nullable();
            $table->json('options')->nullable();
            $table->smallInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_channel_lists');
    }
};
