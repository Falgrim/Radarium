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
        Schema::create('admin_system_prompt_presets', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32);
            $table->string('name', 255);
            $table->string('api_source', 64);
            $table->longText('body')->nullable();
            $table->timestamps();

            $table->index(['scope', 'api_source']);
        });

        if (Schema::hasTable('admin_system_prompts')) {
            $rows = DB::table('admin_system_prompts')->get();
            $now = now();
            foreach ($rows as $row) {
                DB::table('admin_system_prompt_presets')->insert([
                    'scope' => $row->scope,
                    'name' => 'Основной',
                    'api_source' => 'yandexgtp4',
                    'body' => $row->body,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            Schema::drop('admin_system_prompts');
        }
    }

    public function down(): void
    {
        Schema::create('admin_system_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32)->unique();
            $table->longText('body')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (['builder', 'specialist'] as $scope) {
            $first = DB::table('admin_system_prompt_presets')
                ->where('scope', $scope)
                ->orderBy('id')
                ->value('body');
            DB::table('admin_system_prompts')->insert([
                'scope' => $scope,
                'body' => $first ?? '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::dropIfExists('admin_system_prompt_presets');
    }
};
