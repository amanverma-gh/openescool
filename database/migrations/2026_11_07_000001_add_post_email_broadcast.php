<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When an admin emailed this post to every member (one broadcast per cooldown window).
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('emailed_at'));
    }
};
