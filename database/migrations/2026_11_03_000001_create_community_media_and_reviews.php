<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // About-page gallery: uploaded images and YouTube/Vimeo/Loom videos.
        Schema::create('community_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // image | video
            $table->string('path')->nullable(); // image on the public disk
            $table->string('url')->nullable(); // video page URL
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('body');
            $table->boolean('is_hidden')->default(false); // hidden by the owner
            $table->timestamps();

            $table->unique(['community_id', 'user_id']);
            $table->index(['community_id', 'is_hidden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('community_media');
    }
};
