<?php

namespace Database\Factories;

use App\Models\Amenity;
use Illuminate\Database\Eloquent\Factories\Factory; // <-- Import đúng class Factory

class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    public function definition()
    {
        // Rổ từ khóa tiện ích chuẩn 5 sao trên du thuyền
        $luxuryAmenities = [
            'Hồ bơi vô cực', 'Spa & Massage', 'Nhà hàng 3 sao Michelin', 
            'Phòng Gym hiện đại', 'Rạp chiếu phim ngoài trời', 'Sân Golf mini', 
            'Hầm rượu vang', 'Câu lạc bộ trẻ em', 'Casino', 
            'Wifi vệ tinh tốc độ cao', 'Phòng xông hơi khô/ướt', 'Dịch vụ quản gia 24/7',
            'Đài quan sát biển', 'Quầy Bar Lounge', 'Thư viện & Đọc sách'
        ];

        return [
            // Bốc ngẫu nhiên 1 tên và đảm bảo không trùng lặp
            'name' => $this->faker->unique()->randomElement($luxuryAmenities),
        ];
    }
}