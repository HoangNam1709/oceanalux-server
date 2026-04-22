<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->comment('Lý do khách hủy');
            $table->decimal('cancellation_fee', 15, 2)->default(0)->comment('Phí phạt hủy');
            $table->decimal('refund_amount', 15, 2)->default(0)->comment('Số tiền cần hoàn lại');
            $table->string('refund_status')->nullable()->comment('pending: chờ hoàn, refund: đã hoàn');
            $table->timestamp('cancelled_at')->nullable()->comment('Thời điểm bấm hủy');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_reason', 
                'cancellation_fee', 
                'refund_amount', 
                'refund_status', 
                'cancelled_at'
            ]);
        });
    }
};