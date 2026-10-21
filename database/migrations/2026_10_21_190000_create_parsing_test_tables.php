<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parsing_test_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32);
            $table->string('industry', 32)->default('builder');
            $table->string('source', 32)->default('telegram');
            $table->unsignedInteger('sample_size')->default(300);
            $table->unsignedInteger('sampled_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('heuristic_reject_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('model')->nullable();
            $table->string('prompt_label')->nullable();
            $table->longText('prompt_body')->nullable();
            $table->json('provider_options')->nullable();
            $table->text('note')->nullable();
            $table->text('error_message')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('parsing_test_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parsing_test_run_id')->constrained('parsing_test_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('api_channel_post_id')->nullable()->index();
            $table->unsignedBigInteger('api_post_user_id')->nullable()->index();
            $table->string('source', 32);
            $table->string('author_name')->nullable();
            $table->string('author_login')->nullable();
            $table->string('channel_title')->nullable();
            $table->timestamp('post_date')->nullable();
            $table->longText('post_text');
            $table->string('ai_type', 64);
            $table->text('ai_reason')->nullable();
            $table->text('specialities')->nullable();
            $table->longText('model_reply')->nullable();
            $table->timestamps();

            $table->index(['parsing_test_run_id', 'post_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsing_test_messages');
        Schema::dropIfExists('parsing_test_runs');
    }
};
