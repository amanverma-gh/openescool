<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('email_preferences')->nullable()->after('is_admin'); // null = all on
            $table->string('digest_frequency')->default('weekly')->after('email_preferences'); // daily | weekly | off
            $table->timestamp('last_digest_at')->nullable()->after('digest_frequency');
        });

        Schema::table('communities', function (Blueprint $table) {
            $table->json('level_names')->nullable()->after('join_questions');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['email_preferences', 'digest_frequency', 'last_digest_at']));
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn('level_names'));
    }
};
