<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Schedule;
use App\Models\CabinClass;

class SyncCabinSchedules extends Command
{
    // Để cho giống với lệnh bạn hay gõ, mình giữ nguyên signature cũ của bạn
    protected $signature = 'sync:cabin-schedules';

    protected $description = 'Tự động quét và tạo dữ liệu phòng trống dựa trên Total Rooms';

    public function handle()
    {
        $this->info('Đang bắt đầu đồng bộ phòng dựa trên Total Rooms...');
        
        $schedules = Schedule::all();
        $count = 0;

        foreach ($schedules as $schedule) {
            // Lấy các hạng phòng thuộc con tàu của lịch trình này
            $cabins = CabinClass::where('cruise_id', $schedule->cruise_id)->get();
            
            $syncData = [];
            foreach ($cabins as $cabin) {

                $syncData[$cabin->id] = ['available_rooms' => $cabin->total_rooms ?? 0]; 
            }

            // Đồng bộ vào bảng trung gian
            $schedule->cabin_classes()->syncWithoutDetaching($syncData);
            $count++;
        }

        $this->info("Đã đồng bộ thành công dữ liệu phòng cho {$count} lịch trình!");
    }
}