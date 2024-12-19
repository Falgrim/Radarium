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
        Schema::create('specialists', function (Blueprint $table) {
            $table->id();
            $table->integer('api_post_user_id');
            $table->integer('api_channel_post_id');
            $table->string('experience')->nullable(); // Опыт работы по специальности
            $table->string('soft_experience')->nullable(); // Владение ПО
            $table->string('education')->nullable(); // Образование
            $table->string('work_schedule')->nullable(); // Требуемый график работы
            $table->string('total_work_project')->nullable(); // Общая продолжительность работы - проекта
            $table->string('type_of_work')->nullable(); // Тип работы: офис, удаленка, гибрид
            $table->integer('price_by_hour')->nullable(); // Желаемая оплата  за час
            $table->integer('price_by_project')->nullable(); // Желаемая оплата - общая сумма выплат за проект
            $table->integer('price_by_month')->nullable(); // Желаемая оплата - фиксированная оплата за период времени (месяц)
            $table->string('about')->nullable(); // О себе
            $table->string('spec_requirements')->nullable(); // Спец. требования
            $table->string('link_resume')->nullable(); // Ссылка на резюме
            $table->smallInteger('status')->default(0);
            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialists');
    }
};
