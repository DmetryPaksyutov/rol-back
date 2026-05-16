<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('email_verified_at');
            $table->foreignId('avatar_file_id')
                ->nullable()
                ->after('description')
                ->constrained('files')
                ->restrictOnDelete();
        });

        DB::table('users')
            ->whereNull('login')
            ->orWhere('login', '')
            ->update([
                'login' => DB::raw("COALESCE(NULLIF(name, ''), email)"),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_file_id');
            $table->dropColumn(['login', 'description']);
        });
    }
};
