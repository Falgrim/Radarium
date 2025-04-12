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
        Schema::table('payment_tariffs', function (Blueprint $table) {
            $table->string('type')->after('api_data_type'); // Тип тарифа
            $table->string('period'); // Срок действия: 2д, 6ч, 3м
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_tariffs', function (Blueprint $table) {
            //
        });
    }
};
