<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Owners choose whether members may attach photos and GIFs to posts.
        Schema::table('communities', function (Blueprint $table) {
            $table->boolean('allow_images')->default(false);
            $table->boolean('allow_gifs')->default(false);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('attachments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('attachments'));
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn(['allow_images', 'allow_gifs']));
    }
};
