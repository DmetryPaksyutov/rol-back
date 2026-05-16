<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('entity_type', 32)->default('token')->after('name');
        });

        DB::table('entities')->update([
            'entity_type' => DB::raw("CASE WHEN detailable_type = 'App\\\\Models\\\\EntityDrawing' THEN 'shape' ELSE 'token' END"),
        ]);

        Schema::create('entity_token_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('vision_enabled')->default(false);
            $table->boolean('all_players')->default(false);
            $table->json('player_user_ids')->nullable();
            $table->boolean('night_vision_enabled')->default(false);
            $table->decimal('night_vision_range', 10, 2)->default(0);
            $table->boolean('light_enabled')->default(false);
            $table->decimal('light_radius', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('entity_light_sources', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->decimal('radius', 10, 2)->default(6);
            $table->timestamps();
        });

        Schema::create('entity_barriers', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->default('wall');
            $table->boolean('is_open')->default(false);
            $table->decimal('x1', 10, 2)->default(0);
            $table->decimal('y1', 10, 2)->default(0);
            $table->decimal('x2', 10, 2)->default(0);
            $table->decimal('y2', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_barriers');
        Schema::dropIfExists('entity_light_sources');
        Schema::dropIfExists('entity_token_settings');

        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn(['name', 'entity_type']);
        });
    }
};
