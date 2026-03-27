<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:release-expired-bookings')]
#[Description('Command description')]
class ReleaseExpiredBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
{
    // 1. Tìm các đơn hàng đang giữ quá 15 phút mà chưa thanh toán
    $expiredBookings = \App\Models\Booking::where('status', 'holding')
        ->where('hold_expires_at', '<', now())
        ->get();

    if ($expiredBookings->isEmpty()) {
        $this->info("Không có đơn hàng nào hết hạn.");
        return;
    }

    foreach ($expiredBookings as $booking) {
        \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
            // 2. Nhìn vào chi tiết đơn hàng để biết cần trả lại bao nhiêu phòng
           foreach ($booking->details as $detail) {
    $cabin = $detail->cabinClass;
    $cabin->increment('available_rooms', $detail->quantity);
    
    // BẮN TIN HIỆU WEBSOCKET NGAY LẬP TỨC
    event(new RoomReleased($cabin->id, $cabin->available_rooms));
}

            // 3. Cập nhật trạng thái đơn hàng thành 'cancelled' (Đã hủy)
            $booking->update(['status' => 'cancelled']);
            
            $this->info("Đã giải phóng phòng cho mã đơn: {$booking->booking_code}");
        });
    }
}
}
