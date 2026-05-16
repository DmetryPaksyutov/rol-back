<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_drawings', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->string('color', 32)->default('#f97316');
            $table->json('commands');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_drawings');
    }
};
