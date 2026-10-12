<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_speciality_scores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('api_post_user_id');
            $table->unsignedBigInteger('dictionary_speciality_id');
            $table->decimal('score', 12, 4)->default(0);
            $table->decimal('share', 8, 4)->default(0);
            $table->unsignedInteger('support_posts')->default(0);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->boolean('is_selected')->default(false);
            $table->timestamp('last_evidence_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->string('matcher_version', 32)->default('v2');
            $table->timestamps();

            $table->unique(
                ['api_post_user_id', 'dictionary_speciality_id', 'matcher_version'],
                'author_spec_scores_user_spec_version_unique'
            );
            $table->index(['api_post_user_id', 'is_selected', 'rank'], 'author_spec_scores_user_selected_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_speciality_scores');
    }
};
