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
        $posts = \App\Models\ApiChannelPost::get();
        foreach ($posts as $post) {
            $specialist = \App\Models\Specialist::where('api_channel_post_id', $post->id)->first();
            if ($specialist) {
                $specialist->post_date = $post->post_date;
                $specialist->save();
            }

            $companyJob = \App\Models\CompanyJob::where('api_channel_post_id', $post->id)->first();
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

    }
};
