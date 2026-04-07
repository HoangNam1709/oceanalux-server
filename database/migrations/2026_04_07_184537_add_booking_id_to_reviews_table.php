<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('reviews', function (Blueprint $table) {
        // Thêm cột booking_id (nullable để không bị lỗi với dữ liệu cũ nếu có)
        $table->unsignedBigInteger('booking_id')->nullable()->after('cruise_id');
        
        // Khai báo khóa ngoại
        $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('reviews', function (Blueprint $table) {
        $table->dropForeign(['booking_id']);
        $table->dropColumn('booking_id');
    });
}
};
