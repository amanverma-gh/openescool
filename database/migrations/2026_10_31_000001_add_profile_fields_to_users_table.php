<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('bio', 300)->nullable()->after('email');
            $table->string('location', 100)->nullable()->after('bio');
            $table->string('avatar_path')->nullable()->after('location');
            $table->string('google_id')->nullable()->unique()->after('avatar_path');
            $table->boolean('is_admin')->default(false)->after('google_id');
            $table->timestamp('last_seen_at')->nullable();
        });

        // Social sign-ups have no password.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['google_id']);
            $table->dropColumn(['username', 'bio', 'location', 'avatar_path', 'google_id', 'is_admin', 'last_seen_at']);
        });
    }
};
