<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whether search engines may index the community's pages, and its posts.
        Schema::table('communities', function (Blueprint $table) {
            $table->boolean('seo_index')->default(true);
            $table->boolean('seo_index_posts')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('communities', fn (Blueprint $table) => $table->dropColumn(['seo_index', 'seo_index_posts']));
    }
};
