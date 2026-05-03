<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Schedule;
use Carbon\Carbon;

class UpdateCompletedBookings extends Command
{
    protected $signature = 'bookings:update-completed';

    // Mô tả công việc của robot
    protected $description = 'Tự động chuyển đơn hàng sang completed khi chuyến đi kết thúc';

    public function handle()
    {
        $today = Carbon::now();

        // 1. Tìm các lịch trình có ngày về là trong quá khứ
        $scheduleIds = Schedule::where('return_date', '<', $today)->pluck('id');

        // Nếu không có chuyến nào vừa kết thúc, cho robot nghỉ ngơi
        if ($scheduleIds->isEmpty()) {
            $this->info("Không có chuyến đi nào vừa kết thúc hôm nay.");
            return;
        }

        // 2. Cập nhật các đơn hàng thuộc lịch trình đó đang ở trạng thái 'paid' hoặc 'confirmed'
        $updatedCount = Booking::whereIn('schedule_id', $scheduleIds)
            ->whereIn('status', ['confirmed', 'paid'])
            ->update(['status' => 'completed']);

        $this->info("Đã cập nhật tự động {$updatedCount} đơn hàng sang trạng thái Hoàn thành.");
    }
}