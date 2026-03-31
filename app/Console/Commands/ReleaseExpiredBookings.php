<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Events\RoomReleased; 
use App\Events\BookingExpired; // Bắt buộc dùng Event này để đuổi khách
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            return Command::SUCCESS;
        }

        $this->info("Tìm thấy " . $expiredBookings->count() . " đơn hàng hết hạn. Bắt đầu dọn dẹp...");

        foreach ($expiredBookings as $booking) {
            // Dùng Try-Catch để nếu 1 đơn bị lỗi, Robot vẫn chạy tiếp các đơn khác
            try {
                DB::transaction(function () use ($booking) {
                    
                    // 2. Hoàn trả số lượng phòng
                    foreach ($booking->details as $detail) {
                        $cabin = $detail->cabinClass;
                        if ($cabin) {
                            $cabin->increment('available_rooms', $detail->quantity);
                            
                            // Phát tín hiệu: Cập nhật số phòng trống cho người khác mua
                            broadcast(new RoomReleased($cabin->id, $cabin->available_rooms));
                        }
                    }

                    // 3. Cập nhật trạng thái đơn hàng thành 'cancelled'
                    $booking->update(['status' => 'cancelled']);
                    
                    // 4. PHÁT TÍN HIỆU: Cầm loa đuổi thẳng cổ khách hàng khỏi trang Checkout!
                    broadcast(new BookingExpired($booking->id));
                });

                $this->info("Đã giải phóng và phát cảnh báo đuổi khách cho mã đơn: {$booking->booking_code}");

            } catch (\Exception $e) {
                // Ghi log nếu có lỗi xảy ra để truy vết
                $this->error("Lỗi khi giải phóng đơn {$booking->booking_code}: " . $e->getMessage());
                Log::error("Lỗi Robot Dọn Phòng [{$booking->booking_code}]: " . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}