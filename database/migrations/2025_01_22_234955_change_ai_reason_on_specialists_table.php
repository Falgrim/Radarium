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
        Schema::table('specialists', function (Blueprint $table) {
            $table->text('ai_type')->nullable()->change();
            $table->text('ai_reason')->nullable()->change();
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->text('ai_type')->nullable()->change();
            $table->text('ai_reason')->nullable()->change();
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
