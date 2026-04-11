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
        return DB::transaction(function () {
            // Bước 1: Khóa đơn hàng để tránh tranh chấp dữ liệu
            $booking = self::where('id', $this->id)->lockForUpdate()->first();

            // Bước 2: Chỉ xử lý nếu đơn đang ở trạng thái 'holding'
            if (!$booking || $booking->status !== 'holding') {
                return false; 
            }

            // Bước 3: Đổi trạng thái đơn hàng sang 'cancelled' ngay để block các luồng khác
            $booking->status = 'cancelled';
            $booking->save();

            // Bước 4: Hoàn trả số lượng phòng vào bảng TRUNG GIAN (Pivot)
            foreach ($booking->details as $detail) {
                // Tăng số lượng phòng trực tiếp trong bảng pivot dựa trên schedule_id của đơn hàng
                DB::table('cabin_class_schedule')
                    ->where('schedule_id', $booking->schedule_id)
                    ->where('cabin_class_id', $detail->cabin_class_id)
                    ->increment('available_rooms', $detail->quantity);
                
                // Lấy số lượng mới sau khi cộng để bắn Real-time
                $newAvailableCount = DB::table('cabin_class_schedule')
                    ->where('schedule_id', $booking->schedule_id)
                    ->where('cabin_class_id', $detail->cabin_class_id)
                    ->value('available_rooms');

                // Bắn tín hiệu Real-time cho Frontend (React) nảy số lại
                broadcast(new \App\Events\RoomReleased(
                    $detail->cabin_class_id, 
                    $booking->schedule_id, 
                    $newAvailableCount
                ));
            }

            return true;
        });
    }
}