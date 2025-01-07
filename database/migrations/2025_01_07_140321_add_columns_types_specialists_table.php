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
            $table->string('ai_type')->default('');
            $table->text('ai_reason')->default('');
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->string('ai_type')->default('');
            $table->text('ai_reason')->default('');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn('ai_type');
            $table->dropColumn('ai_reason');
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->dropColumn('ai_type');
            $table->dropColumn('ai_reason');
        });
    }
};
