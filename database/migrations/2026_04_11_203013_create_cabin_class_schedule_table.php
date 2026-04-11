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
        Schema::create('cabin_class_schedule', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cabin_class_id')->constrained()->onDelete('cascade');
    $table->foreignId('schedule_id')->constrained()->onDelete('cascade');
    
    // Đây là cột quan trọng nhất: Số phòng trống RIÊNG cho ngày này
    $table->integer('available_rooms'); 
    
    $table->timestamps();
    
    // Đảm bảo không bị trùng lặp: 1 hạng phòng chỉ có 1 dòng cho 1 lịch trình
    $table->unique(['cabin_class_id', 'schedule_id'], 'cabin_sched_unique');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_class_schedule');
    }
};
