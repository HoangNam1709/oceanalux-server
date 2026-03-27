<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    
    protected $guarded = [];
    public function details()
    {
        // Lưu ý: Nếu Model chi tiết của bạn tên khác (VD: OrderDetail), hãy đổi tên Class lại cho đúng nhé
        return $this->hasMany(BookingDetail::class, 'booking_id'); 
    }
}
