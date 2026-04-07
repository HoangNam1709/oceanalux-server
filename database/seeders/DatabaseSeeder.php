<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Cruise;
use App\Models\Itinerary;
use App\Models\Amenity;
use App\Models\Review;
use App\Models\Schedule;
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Tạo 1 Admin & 10 Customer
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('123456'),
            'phone' => '0987654321',
            'role' => 'admin',
        ]);
        User::factory(10)->create();

        // 2. Tạo Tàu (Từ file CruiseSeeder của bạn)
        $this->call([
            CruiseSeeder::class,
        ]);

        // 3. Amenities (Tạo 15 tiện ích mẫu để có đủ kho bốc ngẫu nhiên)
        Amenity::factory(15)->create();
        $allAmenityIds = Amenity::pluck('id');

        // 4. Reviews (Tạo 50 Đánh giá ngẫu nhiên)
        Review::factory(50)->create();
        Schedule::factory(30)->create();
        // ==========================================
        // PHẦN XỬ LÝ DỮ LIỆU ĐA DẠNG CHO TỪNG TÀU
        // ==========================================
        
        // Lấy danh sách tàu vừa được tạo ở bước 2
        $cruises = Cruise::all();

        // Thư viện 3 kịch bản hành trình khác nhau
        $itineraryTemplates = [
            'ha_long' => [
                [
                    'day_number' => 1,
                    'location' => 'Tuần Châu - Vịnh Hạ Long',
                    'description' => 'Khởi hành từ cảng Tuần Châu. Nhận phòng Suite, thưởng thức bữa trưa hải sản cao cấp và khám phá Hang Sửng Sốt.',
                    'activities' => json_encode(['Nhận phòng', 'Ăn trưa hải sản', 'Thăm Hang Sửng Sốt', 'Tiệc Sunsets']),
                ],
                [
                    'day_number' => 2,
                    'location' => 'Đảo Ti Tốp - Hang Luồn',
                    'description' => 'Tập Thái Cực Quyền đón bình minh. Trải nghiệm chèo thuyền Kayak qua Hang Luồn và tắm biển tại Đảo Ti Tốp.',
                    'activities' => json_encode(['Tập Tai Chi', 'Chèo Kayak', 'Tắm biển Ti Tốp', 'Lớp học nấu ăn']),
                ],
                [
                    'day_number' => 3,
                    'location' => 'Làng Chài Cửa Vạn - Cảng',
                    'description' => 'Ngồi đò nan thăm làng chài cổ. Thưởng thức bữa sáng Brunch tự chọn và làm thủ tục rời tàu.',
                    'activities' => json_encode(['Thăm Làng Chài', 'Ăn sáng Brunch', 'Trả phòng']),
                ]
            ],
            'lan_ha' => [
                [
                    'day_number' => 1,
                    'location' => 'Bến Gót - Vịnh Lan Hạ',
                    'description' => 'Lên tàu qua tàu cao tốc. Tận hưởng không gian tĩnh lặng của Vịnh Lan Hạ, tắm bể bơi vô cực và ăn tối Fine Dining.',
                    'activities' => json_encode(['Welcome Drink', 'Tắm bể bơi vô cực', 'Ăn tối Fine Dining', 'Nghe nhạc Jazz']),
                ],
                [
                    'day_number' => 2,
                    'location' => 'Làng cổ Việt Hải',
                    'description' => 'Đạp xe xuyên rừng quốc gia Cát Bà để vào làng cổ Việt Hải. Trải nghiệm massage cá và dùng bữa trưa hữu cơ.',
                    'activities' => json_encode(['Đạp xe xuyên rừng', 'Massage cá', 'Thử rượu ba kích', 'Spa thư giãn']),
                ],
                [
                    'day_number' => 3,
                    'location' => 'Trà Báu - Trở về bờ',
                    'description' => 'Thức dậy với bài tập Yoga trên Sundeck. Tự do bơi lội giữa vịnh xanh mát trước khi tàu nhổ neo về bờ.',
                    'activities' => json_encode(['Tập Yoga', 'Bơi lội tự do', 'Làm thủ tục rời tàu']),
                ]
            ],
            'nha_trang' => [
                [
                    'day_number' => 1,
                    'location' => 'Cảng Nha Trang - Đảo Hòn Tằm',
                    'description' => 'Đón khách bằng xe Limousine. Dạo quanh Vịnh Nha Trang, lặn ngắm san hô và dùng tiệc nướng BBQ trên bãi biển riêng.',
                    'activities' => json_encode(['Limousine đưa đón', 'Lặn san hô', 'Tắm nắng', 'Tiệc BBQ bãi biển']),
                ],
                [
                    'day_number' => 2,
                    'location' => 'Vịnh Ninh Vân',
                    'description' => 'Tiến sâu vào Vịnh Ninh Vân hoang sơ. Khách tự do chơi các trò chơi thể thao dưới nước hoặc thưởng thức High Tea.',
                    'activities' => json_encode(['Lái Jetski', 'Lướt ván biển', 'Trà chiều (High Tea)', 'Xem phim ngoài trời']),
                ],
                [
                    'day_number' => 3,
                    'location' => 'Hòn Mun - Cảng',
                    'description' => 'Ngắm bình minh tuyệt đẹp trên đại dương. Nhâm nhi cà phê sáng, tận hưởng gió biển và kết thúc hành trình.',
                    'activities' => json_encode(['Ngắm bình minh', 'Uống cà phê sáng', 'Mua quà lưu niệm', 'Chào tạm biệt']),
                ]
            ]
        ];

        // 5. Vòng lặp duy nhất: Gắn Tiện ích và Lịch trình cho từng con tàu
        foreach ($cruises as $cruise) {
            
            // --- A. Xử lý gắn Tiện ích ---
            // Bốc ngẫu nhiên 3 đến 6 tiện ích để gắn vào tàu này
            $cruise->amenities()->attach($allAmenityIds->random(rand(3, 6)));

            // --- B. Xử lý gắn Lịch trình ---
            // Bốc ngẫu nhiên 1 trong 3 kịch bản (ha_long, lan_ha, nha_trang)
            $randomTemplateKey = array_rand($itineraryTemplates);
            $selectedTemplate = $itineraryTemplates[$randomTemplateKey];

            // Đổ kịch bản đã chọn vào CSDL cho con tàu này
            foreach ($selectedTemplate as $day) {
                Itinerary::factory()->create([
                    'cruise_id' => $cruise->id,
                    'day_number' => $day['day_number'],
                    'location' => $day['location'],
                    'description' => $day['description'],
                    'activities' => $day['activities'], // Dữ liệu đã được json_encode sẵn
                ]);
            }
            
        }
    }
}