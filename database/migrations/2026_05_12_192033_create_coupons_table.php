<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Lệnh này chỉ tạo thêm bảng 'coupons', hoàn toàn không đụng tới các bảng cũ
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Mã giảm giá, VD: OCEANA500');
            $table->string('title')->comment('Tiêu đề ngắn gọn');
            $table->text('description')->nullable()->comment('Mô tả chi tiết');
            
            $table->decimal('discount_amount', 12, 2)->nullable()->comment('Số tiền giảm');
            $table->decimal('discount_percent', 5, 2)->nullable()->comment('Phần trăm giảm');
            
            $table->decimal('min_order_value', 12, 2)->default(0)->comment('Đơn tối thiểu');
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            
            $table->integer('usage_limit')->nullable()->comment('Giới hạn số lần dùng');
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupons');
    }
};