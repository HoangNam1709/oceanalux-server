<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\CabinClass;
use App\Models\Booking;

class BookingDetail extends Model
{
    protected $guarded = [];

    // 1. Cầu nối sang bảng Hạng Phòng (Giữ nguyên của bạn)
    public function cabinClass() 
    {
        return $this->belongsTo(CabinClass::class, 'cabin_class_id');
    }

    // 2. Cầu nối sang bảng Đơn Hàng (ĐÃ ĐƯỢC TÁCH RA THÀNH HÀM RIÊNG)
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
    
}