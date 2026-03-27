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
        Schema::table('bookings', function (Blueprint $table) {
            // 1. CỘT BẮT BUỘC: Lưu thời gian hết hạn giữ phòng (Chính là 15 phút đếm ngược)
            $table->timestamp('hold_expires_at')->nullable()->after('status');
            
            // 2. CỘT TÙY CHỌN: Nếu hệ thống cho phép "Khách vãng lai" (không đăng nhập) đặt phòng, 
            // thì bạn PHẢI thêm 3 cột này để biết ai đặt. 
            // Còn nếu bắt buộc đăng nhập (đã có user_id) thì có thể bỏ qua 3 cột này.
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone')->nullable()->after('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['hold_expires_at', 'customer_name', 'customer_email', 'customer_phone']);
        });
    }


};
