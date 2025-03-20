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
        // Список платежей
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('payment_tariff_id');
            $table->string('payment_service');
            $table->string('payment_hash');
            $table->integer('sum');
            $table->string('status');
            $table->string('service_pay_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Список тарифов в системе
        Schema::create('payment_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('api_data_type');
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('img_banner')->nullable();
            $table->integer('sum_by_month');
            $table->integer('count_month');
            $table->integer('count_contacts')->default(0);
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });

        // Список оплаченных тарифов пользователя
        Schema::create('user_tariffs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('payment_tariff_id');
            $table->string('api_data_type');
            $table->integer('count_month')->default(0);
            $table->integer('count_contacts')->default(0);
            $table->integer('count_contacts_left')->default(0);
            $table->integer('payment_id')->nullable();
            $table->string('status');
            $table->dateTime('date_start');
            $table->dateTime('date_end');
            $table->string('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Лог просмотра контактов специалистов
        Schema::create('user_open_contacts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_tariff_id')->default(0);
            $table->integer('user_id');
            $table->integer('api_post_user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_tariffs');
        Schema::dropIfExists('user_tariffs');
        Schema::dropIfExists('user_open_contacts');
    }
};
