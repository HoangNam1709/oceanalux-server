<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\CabinClass;
use App\Events\RoomReleased; 
use App\Events\BookingExpired;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredBookings extends Command
{
    protected $signature = 'app:release-expired-bookings';
    protected $description = 'Giải phóng các phòng đã hết hạn giữ chỗ nhưng chưa thanh toán';

    public function handle()
    {
        // 1. Tìm các đơn hàng đang giữ quá hạn
        $expiredBookings = Booking::where('status', 'holding')
            ->where('hold_expires_at', '<', now())
            ->get();

        if ($expiredBookings->isEmpty()) {
            $this->info("Không có đơn hàng nào hết hạn.");
            return Command::SUCCESS;
        }

        $this->info("Tìm thấy " . $expiredBookings->count() . " đơn hàng hết hạn. Bắt đầu dọn dẹp...");

        foreach ($expiredBookings as $booking) {
            try {
                DB::transaction(function () use ($booking) {
                    
                    // 👉 CHỐT CHẶN TỬ THẦN 1: Khóa đơn hàng lại và lấy dữ liệu MỚI NHẤT từ DB
                    $freshBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

                    // 👉 CHỐT CHẶN TỬ THẦN 2: Nếu trong vài giây qua có ai đó (hoặc luồng khác) đã hủy đơn này rồi -> QUAY XE NGAY!
                    if (!$freshBooking || $freshBooking->status !== 'holding') {
                        return; // Bỏ qua, không làm gì cả
                    }

                    // 2. Cập nhật trạng thái đơn hàng THÀNH CANCELLED TRƯỚC
                    $freshBooking->status = 'cancelled';
                    $freshBooking->save();

                    // 3. Hoàn trả số lượng phòng an toàn VÀO BẢNG TRUNG GIAN
foreach ($freshBooking->details as $detail) {
    
    // Tăng số lượng phòng trực tiếp trong bảng pivot dựa trên schedule_id và cabin_class_id
    DB::table('cabin_class_schedule')
        ->where('schedule_id', $freshBooking->schedule_id)
        ->where('cabin_class_id', $detail->cabin_class_id)
        ->increment('available_rooms', $detail->quantity);
        
    // Lấy lại số lượng phòng MỚI NHẤT của ngày đó sau khi đã cộng thêm
    $newAvailableRooms = DB::table('cabin_class_schedule')
        ->where('schedule_id', $freshBooking->schedule_id)
        ->where('cabin_class_id', $detail->cabin_class_id)
        ->value('available_rooms');

    
    broadcast(new RoomReleased($detail->cabin_class_id, $freshBooking->schedule_id, $newAvailableRooms));
}

                    // 4. PHÁT TÍN HIỆU: Đuổi khách khỏi Checkout
                    broadcast(new BookingExpired($freshBooking->id));
                });

                // Check lại xem nó có thực sự bị hủy bởi Robot không để báo Log
                $checkAgain = Booking::find($booking->id);
                if ($checkAgain && $checkAgain->status === 'cancelled') {
                    $this->info("Đã giải phóng và phát cảnh báo đuổi khách cho mã đơn: {$booking->booking_code}");
                }

            } catch (\Exception $e) {
                $this->error("Lỗi khi giải phóng đơn {$booking->booking_code}: " . $e->getMessage());
                Log::error("Lỗi Robot Dọn Phòng [{$booking->booking_code}]: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}