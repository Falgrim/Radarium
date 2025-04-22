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
            $table->string('api_data_type')->nullable()->change();
            $table->integer('price')->after('img_banner')->nullable();
            $table->tinyInteger('is_hot')->default('0');
            $table->dropColumn('count_month');
            $table->dropColumn('sum_by_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
