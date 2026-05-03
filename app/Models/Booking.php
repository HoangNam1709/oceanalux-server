<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB; // Thêm dòng này để dùng Transaction

class Booking extends Model
{
    protected $guarded = [];

    // 1. Mối quan hệ với bảng Chi tiết đặt phòng
    public function details()
    {
        return $this->hasMany(BookingDetail::class, 'booking_id'); 
    }

    // 2. Mối quan hệ với bảng Lịch trình (Schedule)
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

 public function releaseRoom()
    {
        // 1. Ép về chữ thường để chống lỗi viết hoa khi so sánh
        $currentStatus = strtolower($this->status);

        // 2. Kiểm tra trạng thái
        if (!in_array($currentStatus, ['holding', 'confirmed', 'paid'])) {
            \Illuminate\Support\Facades\Log::error("Không thể giải phóng phòng. Trạng thái hiện tại: " . $this->status);
            return false; 
        }

        // 3. Hoàn trả số lượng phòng 
        foreach ($this->details as $detail) {
            \Illuminate\Support\Facades\DB::table('cabin_class_schedule')
                ->where('schedule_id', $this->schedule_id)
                ->where('cabin_class_id', $detail->cabin_class_id)
                ->increment('available_rooms', $detail->quantity ?? 1);
            
            $newAvailableCount = \Illuminate\Support\Facades\DB::table('cabin_class_schedule')
                ->where('schedule_id', $this->schedule_id)
                ->where('cabin_class_id', $detail->cabin_class_id)
                ->value('available_rooms');

            // 4. Bắt lỗi Broadcast 
            try {
                broadcast(new \App\Events\RoomReleased(
                    $detail->cabin_class_id, 
                    $this->schedule_id, 
                    $newAvailableCount
                ));
            } catch (\Exception $e) {
                // Nếu server Socket đang tắt, bỏ qua lỗi để khách vẫn hủy được đơn!
                \Illuminate\Support\Facades\Log::error("Lỗi Socket khi nhả phòng: " . $e->getMessage());
            }
        }

        return true;
    }
}