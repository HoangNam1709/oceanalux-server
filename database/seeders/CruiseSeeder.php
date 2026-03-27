<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Amenity;
use App\Models\Cruise;
use App\Models\CabinClass;
use Illuminate\Support\Facades\Hash;

class CruiseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo tài khoản Admin và Khách hàng
        User::create([
            'name' => 'Quản trị viên',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('12345678'),
            'phone' => '0988888888',
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Khách hàng VIP',
            'email' => 'khachhang@gmail.com',
            'password' => Hash::make('12345678'),
            'phone' => '0911111111',
            'role' => 'customer',
        ]);

        // 2. Tạo các tiện ích (Amenities)
        $amenities = ['Hồ bơi vô cực', 'Wifi miễn phí', 'Spa & Massage', 'Phòng Gym', 'Nhà hàng 5 sao'];
        foreach ($amenities as $item) {
            Amenity::create(['name' => $item]);
        }

        // 3. Tạo 1 chiếc Du thuyền mẫu
        $cruise = Cruise::create([
            'name' => 'Du thuyền Victoria Hạ Long',
            'description' => 'Trải nghiệm đẳng cấp 5 sao trên vịnh Hạ Long với thiết kế hiện đại và sang trọng.',
            'star_rating' => 5,
            'status' => 'active',
        ]);

        // Gắn tiện ích cho tàu này (Ví dụ gắn tiện ích ID 1, 2, 3)
        $cruise->amenities()->attach([1, 2, 3]);

        // 4. Tạo Hạng phòng cho tàu
        CabinClass::create([
            'cruise_id' => $cruise->id,
            'name' => 'Phòng Standard (Tiêu chuẩn)',
            'price' => 2500000,
            'capacity' => 2,
            'total_rooms' => 10,
            'available_rooms' => 10,
        ]);

        CabinClass::create([
            'cruise_id' => $cruise->id,
            'name' => 'Phòng VIP Ocean View',
            'price' => 5500000,
            'capacity' => 2,
            'total_rooms' => 5,
            'available_rooms' => 5,
        ]);
    }
}