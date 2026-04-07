<?php

namespace Database\Factories;

use App\Models\Cruise;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory; // <-- Sửa lại thành import Factory chuẩn

class ScheduleFactory extends Factory
{
    // Đã xóa dòng "use HasFactory;" sai trái ở đây
    protected $model = Schedule::class;

    public function definition()
    {
        // 1. Tạo ngày khởi hành ngẫu nhiên từ hôm nay đến 6 tháng tới
        $departure = $this->faker->dateTimeBetween('now', '+6 months');
        
        // 2. Ngày về (return_date) sẽ sau ngày khởi hành từ 3 đến 14 ngày
        $return = (clone $departure)->modify('+' . rand(3, 14) . ' days');

        return [
            // Bốc ngẫu nhiên ID của 1 con tàu đang có trong bảng cruises
            'cruise_id' => Cruise::inRandomOrder()->first()->id, 
            
            'departure_date' => $departure->format('Y-m-d H:i:s'),
            'return_date' => $return->format('Y-m-d H:i:s'),
            
            // Random trạng thái (bạn có thể đổi lại các chữ này cho khớp với logic của bạn, ví dụ: 0, 1, 2)
            'status' => $this->faker->randomElement(['pending', 'active', 'completed', 'cancelled']), 
        ];
    }
}