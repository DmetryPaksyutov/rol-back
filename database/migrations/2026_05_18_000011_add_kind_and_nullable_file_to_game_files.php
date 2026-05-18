<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_files', function (Blueprint $table) {
            $table->string('kind', 16)->default('image')->after('game_id');
        });

        DB::table('game_files')->update(['kind' => 'image']);

        Schema::table('game_files', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->foreignId('file_id')->nullable()->change();
            $table->foreign('file_id')->references('id')->on('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_files', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')->references('id')->on('files')->restrictOnDelete();
            $table->dropColumn('kind');
        });
    }
};
