<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo limits are now one platform-wide setting (Admin settings, max 1 MB),
 * not a per-community choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn(['max_images', 'max_upload_kb']));
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_images')->default(4);
            $table->unsignedInteger('max_upload_kb')->default(1024);
        });
    }
};
