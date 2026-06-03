<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_videos', function (Blueprint $table) {
            $table->id();
            $table->text('idea');
            $table->longText('script');
            $table->longText('copy_title')->nullable();
            $table->longText('copy_description')->nullable();
            $table->longText('copy_cta')->nullable();
            $table->longText('copy_hashtags')->nullable();
            $table->longText('copy_tags')->nullable();
            $table->longText('video_phrases')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_videos');
    }
};
