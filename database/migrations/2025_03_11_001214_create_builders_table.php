<?php

use App\Models\BuilderReview;
use Illuminate\Database\Eloquent\Builder;
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
        Schema::create('builders', function (Blueprint $table) {
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
            $table->string('contact_info')->nullable();
            $table->string('link_resume')->nullable(); // Ссылка на резюме
            $table->smallInteger('status')->default(0);
            $table->dateTime('post_date')->nullable();
            $table->string('ai_type')->default('');
            $table->text('ai_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('builder_specialities', function (Blueprint $table) {
            $table->id();
            $table->integer('builder_id');
            $table->integer('dictionary_speciality_id');
            $table->timestamps();
        });

        Schema::create('builder_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('api_post_user_id');
            $table->integer('user_id');
            $table->integer('builder_id');
            $table->text('text');
            $table->smallInteger('rating')->default(0);
            $table->tinyInteger('can_edit')->default(1)->comment('Возможность редактировать только 1 раз');
            $table->smallInteger('status')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('builder_review_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('builder_review_id')->constrained(table: BuilderReview::table())->cascadeOnDelete();
            $table->string('title');
            $table->string('value');
        });

        Schema::table('dictionary_specialities', function (Blueprint $table) {
            $table->smallInteger('api_data_type_id')->unsigned()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('builders');
        Schema::dropIfExists('builder_specialities');
        Schema::dropIfExists('builder_reviews');
        Schema::dropIfExists('builder_review_custom_fields');

        Schema::table('dictionary_specialities', function (Blueprint $table) {
            $table->dropColumn('api_data_type_id');
        });
    }
};
