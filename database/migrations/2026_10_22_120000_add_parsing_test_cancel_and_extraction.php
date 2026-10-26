<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parsing_test_runs', function (Blueprint $table) {
            $table->unsignedInteger('extraction_error_count')->default(0)->after('error_count');
            $table->timestamp('cancel_requested_at')->nullable()->after('finished_at');
            $table->unsignedInteger('pid')->nullable()->after('cancel_requested_at');
        });

        Schema::table('parsing_test_messages', function (Blueprint $table) {
            $table->text('extraction_error')->nullable()->after('model_reply');
        });
    }

    public function down(): void
    {
        Schema::table('parsing_test_messages', function (Blueprint $table) {
            $table->dropColumn('extraction_error');
        });

        Schema::table('parsing_test_runs', function (Blueprint $table) {
            $table->dropColumn(['extraction_error_count', 'cancel_requested_at', 'pid']);
        });
    }
};
