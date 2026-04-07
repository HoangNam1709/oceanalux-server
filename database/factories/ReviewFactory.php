<?php

namespace Database\Factories;

use App\Models\Cruise;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition()
    {
        // Rổ bình luận mồi (nghe rất giống khách đi du thuyền thật)
        $comments = [
            'Trải nghiệm tuyệt vời, nhân viên phục vụ rất chu đáo và nhiệt tình!',
            'Du thuyền siêu đẹp, đồ ăn ngon, chắc chắn gia đình mình sẽ quay lại.',
            'Phòng ban công view biển cực chill, góc nào check-in cũng xịn xò.',
            'Dịch vụ 5 sao chuẩn quốc tế, rất đáng đồng tiền bát gạo.',
            'Hành trình thú vị, phòng ốc sạch sẽ và sang trọng. Spa trên tàu rất thư giãn.',
            'Bữa tối Michelin thực sự xuất sắc. Một kỳ nghỉ kỷ niệm ngày cưới hoàn hảo!',
            'Tàu chạy êm, cảnh Vịnh siêu đẹp. Rất recommend mọi người nên thử một lần trong đời.'
        ];

        return [
            // Bốc ngẫu nhiên 1 User (đóng vai người đánh giá)
            'user_id' => User::inRandomOrder()->first()->id,
            
            // Bốc ngẫu nhiên 1 Cruise (đóng vai tàu được đánh giá)
            'cruise_id' => Cruise::inRandomOrder()->first()->id,
            
            // Random số sao từ 4 đến 5 (Đã là Luxury Cruise thì hiếm khi có 1-2 sao)
            'rating' => $this->faker->numberBetween(4, 5),
            
            // Bốc ngẫu nhiên 1 câu bình luận
            'comment' => $this->faker->randomElement($comments),
        ];
    }
}