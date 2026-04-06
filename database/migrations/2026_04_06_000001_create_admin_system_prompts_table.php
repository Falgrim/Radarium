<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_system_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32)->unique();
            $table->longText('body')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (['builder', 'specialist'] as $scope) {
            DB::table('admin_system_prompts')->insert([
                'scope' => $scope,
                'body' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_system_prompts');
    }
};
