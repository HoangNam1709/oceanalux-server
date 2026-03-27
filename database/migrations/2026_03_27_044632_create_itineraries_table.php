<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cruise_id')->constrained()->cascadeOnDelete();
            $table->integer('day_number'); // Ngày số mấy (1, 2, 3...)
            $table->string('location'); // Địa điểm hoặc Tiêu đề (VD: Cảng Tuần Châu - Vịnh Hạ Long)
            $table->text('description')->nullable(); // Mô tả chi tiết các hoạt động
            $table->json('activities')->nullable(); // Mảng các hoạt động nhỏ (VD: ["Ăn trưa", "Tắm biển"])
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('itineraries');
    }
};
