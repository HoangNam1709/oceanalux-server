<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cruise extends Model
{
    use HasFactory;

    // Cho phép nạp dữ liệu vào tất cả các cột
    protected $guarded = [];

    // Nối với bảng Tiện ích (Nhiều - Nhiều)
    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'cruise_amenities', 'cruise_id', 'amenity_id');
    }

    // THÊM MỚI: Nối với bảng Hạng phòng (1 Tàu có Nhiều Hạng phòng)
    public function cabinClasses()
    {
        return $this->hasMany(CabinClass::class);
    }
    // Nối với bảng thư viện ảnh
    public function images()
    {
        return $this->hasMany(CruiseImage::class);
    }
    // Nối với bảng đánh giá
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
    public function itineraries()
    {
        return $this->hasMany(Itinerary::class)->orderBy('day_number');
    }
}