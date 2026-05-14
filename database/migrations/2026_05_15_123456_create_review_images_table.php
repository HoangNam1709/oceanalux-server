<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('review_images', function (Blueprint $table) {
            $table->id();
            // CHÍNH LÀ DÒNG NÀY ĐANG BỊ THIẾU TRONG DB CỦA BẠN:
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            
            $table->string('image_path');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('review_images');
    }
};