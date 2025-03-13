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
        Schema::table('builders', function (Blueprint $table) {
            $table->text('experience')->nullable()->change();
            $table->text('soft_experience')->nullable()->change();
            $table->text('education')->nullable()->change();
            $table->text('about')->nullable()->change();
            $table->text('spec_requirements')->nullable()->change();
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
