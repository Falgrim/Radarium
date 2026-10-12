<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_post_speciality_evidence', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('api_channel_post_id');
            $table->unsignedBigInteger('dictionary_speciality_id');
            $table->decimal('text_score', 8, 4)->default(0);
            $table->decimal('ai_score', 8, 4)->default(0);
            $table->decimal('combined', 8, 4)->default(0);
            $table->string('matcher_version', 32)->default('v2');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(
                ['api_channel_post_id', 'dictionary_speciality_id', 'matcher_version'],
                'bps_evidence_post_spec_version_unique'
            );
            $table->index(['api_channel_post_id', 'matcher_version'], 'bps_evidence_post_version_idx');
            $table->index(['dictionary_speciality_id'], 'bps_evidence_spec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builder_post_speciality_evidence');
    }
};
