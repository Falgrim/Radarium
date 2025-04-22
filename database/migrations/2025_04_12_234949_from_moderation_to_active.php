<?php

use App\Enum\ApiPostAiStatusEnum;
use App\Models\CompanyJob;
use App\Models\Specialist;
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
        Specialist::where('status', ApiPostAiStatusEnum::InModeration)->update([
            'status' => ApiPostAiStatusEnum::Active,
        ]);

        CompanyJob::where('status', ApiPostAiStatusEnum::InModeration)->update([
            'status' => ApiPostAiStatusEnum::Active,
        ]);

        \App\Models\Builder::where('status', ApiPostAiStatusEnum::InModeration)->update([
            'status' => ApiPostAiStatusEnum::Active,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
