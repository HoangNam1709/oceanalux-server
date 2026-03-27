<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Events\RoomReleased; 
use App\Events\BookingUpdated;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredBookings extends Command
{
    protected $signature = 'app:release-expired-bookings';
    protected $description = 'Giải phóng các phòng đã hết hạn giữ chỗ nhưng chưa thanh toán';

    public function handle()
    {
        // 1. Tìm các đơn hàng đang giữ quá hạn (holding + hết thời gian)
        $expiredBookings = Booking::with('details.cabinClass')
            ->where('status', 'holding')
            ->where('hold_expires_at', '<', now())
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info("Không có đơn hàng nào hết hạn.");
            return 0;
        }

        foreach ($expiredBookings as $booking) {
            DB::transaction(function () use ($booking) {
                // 2. Duyệt qua chi tiết đơn hàng để hoàn trả số lượng phòng
                foreach ($booking->details as $detail) {
                    $cabin = $detail->cabinClass;
                    if ($cabin) {
                        // Cộng lại số phòng vào Database
                        $cabin->increment('available_rooms', $detail->quantity);
                        
                        // 3. PHÁT TÍN HIỆU: Cập nhật số phòng trống cho Trang Chủ
                        broadcast(new RoomReleased($cabin->id, $cabin->available_rooms));
                    }
                }

                // 4. Cập nhật trạng thái đơn hàng thành 'cancelled'
                $booking->update(['status' => 'cancelled']);
                
                // 5. PHÁT TÍN HIỆU: Thông báo cho trang Checkout đơn hàng này đã chết
                // Đổi event(...) thành broadcast(...) để chắc chắn bay lên Websocket
                broadcast(new BookingUpdated($booking->id, $booking->hold_expires_at, 'cancelled'));
                
                $this->info("Đã giải phóng phòng cho mã đơn: {$booking->booking_code}");
            });
        }

        return 0;
    }
}