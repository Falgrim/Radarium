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
            $table->dateTime('post_date')->nullable();
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->dateTime('post_date')->nullable();
        });

        $posts = \App\Models\ApiChannelPost::get();
        foreach ($posts as $post) {
            $specialist = \App\Models\Specialist::where('api_post_user_id', $post->id)->first();
            if ($specialist) {
                $specialist->post_date = $post->post_date;
                $specialist->save();
            }

            $companyJob = \App\Models\CompanyJob::where('api_post_user_id', $post->id)->first();
            if ($companyJob) {
                $companyJob->post_date = $post->post_date;
                $companyJob->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn('post_date');
        });

        Schema::table('company_jobs', function (Blueprint $table) {
            $table->dropColumn('post_date');
        });
    }
};
