<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_chat_messages', function (Blueprint $table) {
            $table->string('type', 32)->default('message')->after('user_id');
            $table->json('payload')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('game_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'payload']);
        });
    }
};
