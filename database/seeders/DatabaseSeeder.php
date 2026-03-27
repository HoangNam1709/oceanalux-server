<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Schedule;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Gọi CruiseSeeder để tạo Tàu (Cruises) và Hạng phòng (CabinClasses) trước
        $this->call([
            CruiseSeeder::class,
        ]);

        // 2. Sau khi có Tàu rồi mới tạo Lịch trình (Schedule) có ID = 1
        // Lưu ý: Đảm bảo trong CruiseSeeder bạn đã tạo con tàu có ID = 1
       // Trong file DatabaseSeeder.php, sửa lại đoạn tạo Schedule như sau:

Schedule::create([
    'id' => 1,
    'cruise_id' => 1,
    'departure_date' => now()->addDays(7),
    'return_date' => now()->addDays(9), // ĐỔI TỪ arrival_date THÀNH return_date
    'status' => 'available'
]);
    }
}