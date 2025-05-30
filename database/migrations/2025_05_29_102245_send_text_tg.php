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
        Schema::table('api_post_users', function (Blueprint $table) {
            $table->smallInteger('send_new_msg')->default(0);
        });

        Schema::create('mailing_messages', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->smallInteger('is_main')->default(0);
            $table->dateTime('date_send');
            $table->string('status', 20);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mailing_message_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('mailing_message_id');
            $table->integer('api_post_user_id');
            $table->integer('msg_id');
            $table->string('status', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_post_users', function (Blueprint $table) {
            $table->dropColumn('send_new_msg');
        });

        Schema::dropIfExists('mailing_messages');
        Schema::dropIfExists('mailing_message_logs');
    }
};
