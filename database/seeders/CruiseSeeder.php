<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CruiseSeeder extends Seeder
{
    public function run()
    {
        $cruises = [
            [
                'name' => 'Scarlet Pearl Cruises',
                'description' => 'Trải nghiệm đẳng cấp trên Vịnh Lan Hạ với thiết kế mang dáng dấp của một siêu du thuyền tỷ phú. Tàu trang bị bảo tàng ngọc trai trên boong, nhà hàng Tahiti và 100% cabin có ban công view biển tuyệt đẹp.',
                'thumbnail' => 'https://images.unsplash.com/photo-1552465011-b4e21bf6e79a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Heritage Cruises Binh Chuan',
                'description' => 'Mang đậm phong cách kiến trúc Đông Dương (Indochine) cổ điển. Đây là du thuyền boutique đầu tiên trên Vịnh Bắc Bộ, nơi du khách vừa nghỉ dưỡng 5 sao vừa khám phá di sản văn hóa, lịch sử Việt Nam.',
                'thumbnail' => 'https://images.unsplash.com/photo-1528127269322-539801943592?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Emperor Cruises Nha Trang',
                'description' => 'Lấy cảm hứng từ cuộc sống vương giả của Vua Bảo Đại. Du thuyền mang đến trải nghiệm bao trọn gói (All-inclusive) trên vịnh Nha Trang với quản gia riêng, thưởng thức nhạc sống và ẩm thực cung đình.',
                'thumbnail' => 'https://images.unsplash.com/photo-1600862083103-6250b731d102?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Aqua Mekong',
                'description' => 'Nổi bật như một khách sạn 5 sao nổi di chuyển êm ái trên dòng sông Mekong hùng vĩ. Tàu có thiết kế hiện đại, hồ bơi vô cực trên mạn tàu và các tour thám hiểm văn hóa miệt vườn độc quyền.',
                'thumbnail' => 'https://images.unsplash.com/photo-1504626815347-4948a2113337?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ambassador Cruise',
                'description' => 'Siêu du thuyền lớn nhất vịnh Hạ Long với sức chứa lên đến 500 khách. Nổi bật với thác nước kính cường lực, bể bơi sục Jacuzzi ngoài trời và cầu kính check-in vươn ra mũi tàu.',
                'thumbnail' => 'https://images.unsplash.com/photo-1520625313364-c75cce92e2eb?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Stellar of the Seas',
                'description' => 'Biểu tượng của sự xa hoa trẻ trung trên Vịnh Lan Hạ. Tàu sở hữu hầm cigar và rượu vang, sân golf mini, và một bể bơi theo mùa rộng lớn ngay giữa boong thượng.',
                'thumbnail' => 'https://images.unsplash.com/photo-1548651806-b3e1577e923e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080',
                'star_rating' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('cruises')->insert($cruises);
    }
}