<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide settings an admin edits in the app (secrets are stored encrypted).
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('communities', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_images')->default(4);
            $table->unsignedInteger('max_upload_kb')->default(1024);
            $table->boolean('allow_comment_media')->default(false);
        });

        // One photo (storage path) or one GIPHY GIF ("giphy:<id>") per comment.
        Schema::table('comments', function (Blueprint $table) {
            $table->string('attachment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comments', fn (Blueprint $table) => $table->dropColumn('attachment'));
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn(['max_images', 'max_upload_kb', 'allow_comment_media']));
        Schema::dropIfExists('app_settings');
    }
};
