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
        Schema::create('moderation_alerts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->smallInteger('is_system')->default(0);
            $table->string('table_name');
            $table->integer('table_row_id');
            $table->text('description')->nullable();
            $table->text('comment')->nullable();
            $table->smallInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_alerts');
    }
};
