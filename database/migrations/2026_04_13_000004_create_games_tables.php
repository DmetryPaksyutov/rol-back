<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('key', 32)->unique();
            $table->foreignId('image')->nullable()->constrained('files')->nullOnDelete();
            $table->unsignedBigInteger('active_page_id')->nullable();
            $table->timestamps();

            $table->index(['owner', 'name']);
        });

        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'user_id']);
        });

        Schema::create('game_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'invited_user_id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('name');
            $table->string('path')->nullable();
            $table->timestamps();
        });

        Schema::create('layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('name');
            $table->integer('level')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('interactive')->default(false);
            $table->timestamps();

            $table->index(['game_id', 'level']);
        });

        Schema::create('game_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('name');
            $table->string('path')->nullable();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();
            $table->decimal('width', 10, 2)->default(0);
            $table->decimal('height', 10, 2)->default(0);
            $table->decimal('x', 10, 2)->default(0);
            $table->decimal('y', 10, 2)->default(0);
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->boolean('block')->default(false);
            $table->json('controller_user_ids')->nullable();
            $table->nullableMorphs('detailable');
            $table->timestamps();

            $table->index(['page_id', 'layer_id']);
        });

        Schema::create('game_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('active_page_id')->references('id')->on('pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['active_page_id']);
        });

        Schema::dropIfExists('game_chat_messages');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('game_files');
        Schema::dropIfExists('layers');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('game_invitations');
        Schema::dropIfExists('game_players');
        Schema::dropIfExists('games');
    }
};
