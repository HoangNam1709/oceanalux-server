<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('cabin_images', function (Blueprint $table) {
        $table->id();
        // Giả sử bảng hạng phòng của bạn tên là cabin_classes, nếu là cabins thì sửa lại nhé
        $table->foreignId('cabin_class_id')->constrained('cabin_classes')->onDelete('cascade');
        $table->string('image_url');
        $table->boolean('is_thumbnail')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_images');
    }
};
