<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An uploaded lesson video (private disk), as an alternative to video_url.
        Schema::table('lessons', fn (Blueprint $table) => $table->string('video_path')->nullable()->after('video_url'));
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('video_path'));
    }
};
