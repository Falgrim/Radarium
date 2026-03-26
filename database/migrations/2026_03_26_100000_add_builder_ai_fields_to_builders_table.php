<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->text('service_type_raw')->nullable()->after('region');
            $table->json('service_types')->nullable()->after('service_type_raw');
            $table->json('object_types')->nullable()->after('service_types');
            $table->text('performer_type_raw')->nullable()->after('object_types');
            $table->string('performer_type', 50)->nullable()->after('performer_type_raw');
            $table->json('equipment_skills_json')->nullable()->after('performer_type');
            $table->string('legal_form', 20)->nullable()->after('equipment_skills_json');
            $table->string('location_city', 255)->nullable()->after('legal_form');
            $table->string('location_region', 255)->nullable()->after('location_city');
            $table->text('price_comment')->nullable()->after('location_region');
        });
    }

    public function down(): void
    {
        Schema::table('builders', function (Blueprint $table) {
            $table->dropColumn([
                'service_type_raw',
                'service_types',
                'object_types',
                'performer_type_raw',
                'performer_type',
                'equipment_skills_json',
                'legal_form',
                'location_city',
                'location_region',
                'price_comment',
            ]);
        });
    }
};
