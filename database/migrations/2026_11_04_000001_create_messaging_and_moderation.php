<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Held for review: can read, can't post or message until an admin decides.
            $table->timestamp('restricted_at')->nullable()->after('last_digest_at');
            $table->string('restricted_reason')->nullable()->after('restricted_at');
            // Banned: can't sign in at all.
            $table->timestamp('banned_at')->nullable()->after('restricted_reason');
        });

        foreach (['posts', 'comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_spam')->default(false)->index());
        }

        // One row per pair of users; user_a_id is always the smaller id.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('a_read_at')->nullable();
            $table->timestamp('b_read_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_a_id', 'user_b_id']);
            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('fingerprint', 64)->index(); // hash of the normalised body, to catch copy-paste blasts
            $table->boolean('is_spam')->default(false);
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['blocker_id', 'blocked_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->constrained('users')->cascadeOnDelete();
            $table->morphs('reportable'); // post | comment | message
            $table->string('reason')->default('spam');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_id', 'reportable_type', 'reportable_id']);
            $table->index(['reported_user_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        foreach (['posts', 'comments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_spam'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['restricted_at', 'restricted_reason', 'banned_at']));
    }
};
