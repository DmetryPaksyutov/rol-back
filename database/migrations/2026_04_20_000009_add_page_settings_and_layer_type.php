<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->string('type', 32)->default('token')->after('name');
            $table->index(['game_id', 'type']);
        });

        DB::table('layers')->whereRaw('LOWER(name) = ?', ['map'])->update(['type' => 'map']);

        Schema::create('page_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->unique()->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('canvas_width')->default(3200);
            $table->unsignedInteger('canvas_height')->default(2200);
            $table->boolean('grid_enabled')->default(true);
            $table->unsignedInteger('grid_cell_size')->default(100);
            $table->string('lighting_type', 32)->default('off');
            $table->timestamps();
        });

        $now = now();
        $settings = DB::table('pages')->select('id')->get()->map(fn ($page) => [
            'page_id' => $page->id,
            'canvas_width' => 3200,
            'canvas_height' => 2200,
            'grid_enabled' => true,
            'grid_cell_size' => 100,
            'lighting_type' => 'off',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($settings) {
            DB::table('page_settings')->insert($settings);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_settings');

        Schema::table('layers', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
