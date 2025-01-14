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
        Schema::create('company_jobs', function (Blueprint $table) {
            $table->id();
            $table->integer('api_post_user_id');
            $table->integer('api_channel_post_id');
            $table->string('position')->nullable(); // Должность
            $table->string('company_name')->nullable(); // Название компании
            $table->integer('min_price')->nullable(); // Предлагаемый оклад
            $table->integer('max_price')->nullable(); // Предлагаемый оклад
            $table->text('duty')->nullable(); // Обязанности
            $table->text('requirement')->nullable(); // Требования
            $table->string('work_schedule')->nullable(); // График
            $table->string('type_of_work')->nullable(); // Тип работы: офис, удаленка, гибрид
            $table->text('description')->nullable(); // Описание проекта
            $table->string('period')->nullable(); // Срок найма
            $table->string('extra_conditions')->nullable(); // Доп. условия

            $table->smallInteger('status')->default(0);
            $table->timestamps();

            $table->softDeletes();
        });

        Schema::table('api_post_users', function (Blueprint $table) {
            $table->tinyInteger('is_company')->default(0);
        });

        Schema::table('api_channels', function (Blueprint $table) {
            $table->tinyInteger('is_company')->default(0);
        });

        Schema::create('company_job_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('company_job_id');
            $table->text('text');
            $table->smallInteger('rating')->default(0);
            $table->tinyInteger('can_edit')->default(1)->comment('Возможность редактировать только 1 раз');
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
        Schema::dropIfExists('company_jobs');

        Schema::table('api_post_users', function (Blueprint $table) {
            $table->dropColumn('is_company');
        });

        Schema::table('api_channels', function (Blueprint $table) {
            $table->dropColumn('is_company');
        });

        Schema::dropIfExists('company_job_reviews');
    }
};
