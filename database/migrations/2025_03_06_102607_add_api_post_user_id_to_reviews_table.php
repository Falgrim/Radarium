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
        Schema::table('reviews', function (Blueprint $table) {
            $table->integer('api_post_user_id')->after('id');
        });

        Schema::table('company_job_reviews', function (Blueprint $table) {
            $table->integer('api_post_user_id')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('api_post_user_id');
        });

        Schema::table('company_job_reviews', function (Blueprint $table) {
            $table->dropColumn('api_post_user_id');
        });
    }
};
