<?php

use App\Models\CompanyJobReview;
use App\Models\Review;
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
        Schema::create('review_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained(table: Review::table())->cascadeOnDelete();
            $table->string('title');
            $table->string('value');
        });

        Schema::create('company_job_review_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_job_review_id')->constrained(table: CompanyJobReview::table())->cascadeOnDelete();
            $table->string('title');
            $table->string('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_custom_fields');
    }
};
