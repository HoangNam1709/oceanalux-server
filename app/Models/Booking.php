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

    /**
     * 3.  Hàm xử lý hủy và giải phóng phòng AN TOÀN TUYỆT ĐỐI
     * Chống lỗi cộng dồn phòng khi Robot và User cùng hủy lúc.
     */
    public function releaseRoom()
    {
        return DB::transaction(function () {
            // Bước 1: Query lại chính đơn hàng này và KHÓA nó lại (lockForUpdate)
            // Nếu con Robot đang xử lý đơn này, user bấm hủy trên web sẽ phải đứng đợi
            $booking = self::where('id', $this->id)->lockForUpdate()->first();

            // Bước 2: KIỂM TRA CHỐT CHẶN (Idempotency)
            // Chỉ khi trạng thái đang là 'holding' thì mới được nhả phòng. 
            // Nếu đã 'cancelled' rồi thì tuyệt đối quay xe, không làm gì cả.
            if (!$booking || $booking->status !== 'holding') {
                return false; 
            }

            // Bước 3: Đổi trạng thái ngay lập tức để block các luồng khác
            $booking->status = 'cancelled';
            $booking->save();

            // Bước 4: Tìm hạng phòng và trả lại số lượng
            foreach ($booking->details as $detail) {
                // Khóa luôn bảng CabinClass để không bị xung đột với người đang đặt phòng mới
                $cabin = CabinClass::where('id', $detail->cabin_class_id)->lockForUpdate()->first();
                
                if ($cabin) {
                    $cabin->available_rooms += $detail->quantity;
                    $cabin->save();
                    
                    // Dùng $booking->schedule_id vì chúng ta đang xử lý cái $booking ở ngay phía trên
                    broadcast(new \App\Events\RoomReleased($cabin->id, $cabin->available_rooms, $booking->schedule_id));
                }
            }

            return true;
        });
    }
}