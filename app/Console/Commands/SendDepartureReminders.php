<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Mail\BookingReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendDepartureReminders extends Command
{
    // Tên lệnh để chạy trong Terminal
    protected $signature = 'reminders:send-departure';

    protected $description = 'Quét và gửi email nhắc nhở cho các Booking trước 48h khởi hành';

   public function handle()
    {
        // TỐI ƯU: Quét theo NGÀY thay vì quét theo GIỜ để không bao giờ bị trượt mục tiêu
        // Lấy ngày của 2 ngày tới (Ví dụ hôm nay 14/05 thì targetDate sẽ là 16/05)
        $targetDate = Carbon::now()->addDays(2)->format('Y-m-d'); 

        // Lọc các đơn thỏa mãn
        $bookings = Booking::with(['schedule.cruise', 'details.cabinClass'])
            ->where('status', 'paid')
            ->whereNull('reminded_at') // Đảm bảo chưa gửi bao giờ
            ->whereHas('schedule', function ($query) use ($targetDate) {
                $query->whereDate('departure_date', $targetDate);
            })
            ->get();

        if ($bookings->isEmpty()) {
            $this->info("Không có đơn hàng nào cần nhắc nhở lúc này.");
            return;
        }

        foreach ($bookings as $booking) {
            try {
                // Đẩy mail vào Queue để gửi ngầm
                Mail::to($booking->customer_email)->queue(new BookingReminderMail($booking));

                // Đánh dấu đã gửi bằng giờ hiện tại để chặn gửi trùng lặp
                $booking->update(['reminded_at' => now()]);

                Log::info("Đã gửi email nhắc 48h cho đơn: " . $booking->booking_code);
                $this->info("Thành công: " . $booking->booking_code);

            } catch (\Exception $e) {
                Log::error("Lỗi gửi nhắc nhở 48h đơn {$booking->booking_code}: " . $e->getMessage());
                $this->error("Lỗi: " . $booking->booking_code);
            }
        }
    }
}