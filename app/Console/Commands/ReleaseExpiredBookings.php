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

                    // 3. Hoàn trả số lượng phòng an toàn
                    foreach ($freshBooking->details as $detail) {
                        // Khóa luôn bảng Hạng Phòng để không ai đặt lẹm vào lúc này
                        $cabin = CabinClass::where('id', $detail->cabin_class_id)->lockForUpdate()->first();
                        
                        if ($cabin) {
                            $cabin->available_rooms += $detail->quantity;
                            
                            // (Tùy chọn) Chống cộng lố tổng số phòng nếu bạn có cột total_rooms
                            // if (isset($cabin->total_rooms) && $cabin->available_rooms > $cabin->total_rooms) {
                            //     $cabin->available_rooms = $cabin->total_rooms;
                            // }
                            
                            $cabin->save();
                            
                            // Phát tín hiệu: Cập nhật số phòng trống cho người khác mua
                            broadcast(new RoomReleased($cabin->id, $cabin->available_rooms));
                        }
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