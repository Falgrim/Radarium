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
        Schema::create('dictionary_specialities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('specialist_specialities', function (Blueprint $table) {
            $table->id();
            $table->integer('specialist_id');
            $table->integer('dictionary_speciality_id');
            $table->timestamps();
        });

        Schema::create('company_job_specialities', function (Blueprint $table) {
            $table->id();
            $table->integer('company_job_id');
            $table->integer('dictionary_speciality_id');
            $table->timestamps();
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->text('contact_info');
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->text('contact_info');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dictionary_specialties');
        Schema::dropIfExists('specialist_specialties');
        Schema::dropIfExists('company_job_specialties');

        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn('contact_info');
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->dropColumn('contact_info');
        });
    }
};
