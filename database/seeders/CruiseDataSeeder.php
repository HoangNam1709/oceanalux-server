<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cruise;
use App\Models\CabinClass;
use Illuminate\Support\Facades\DB;

class CruiseDataSeeder extends Seeder
{
    public function run()
    {
        $cruiseNames = [
            'Diana Luxury', 'Indochine Grand', 'Dragon Legend', 'Heritage Line', 
            'Stellar Cruises', 'Paradise Elegance', 'Ambassador Signature', 
            'Orchid Trendy', 'Mon Chéri', 'Capella Cruise'
        ];

        $cabinTypes = ['Standard', 'Deluxe', 'Executive', 'Suite', 'Presidential'];

        foreach ($cruiseNames as $name) {
            DB::beginTransaction();
            try {
                // 1. Tạo Du thuyền
                $cruise = Cruise::create([
                    'name' => $name,
                    'thumbnail' => '/images/tau-' . rand(1, 5) . '.jpg',
                    'destination' => 'Vịnh Hạ Long',
                    'duration_days' => rand(2, 3),
                    'duration_nights' => rand(1, 2),
                    'description' => 'Du thuyền 5 sao đẳng cấp quốc tế tại Vịnh Hạ Long.',
                    'star_rating' => 5,
                    'status' => 'active'
                ]);

                // 2. Tạo 5-7 hạng phòng cho mỗi du thuyền
                $numCabins = rand(5, 7);
                for ($i = 0; $i < $numCabins; $i++) {
                    $cabin = CabinClass::create([
                        'cruise_id' => $cruise->id,
                        'name' => $cabinTypes[array_rand($cabinTypes)] . ' ' . ($i + 1),
                        'price' => rand(2000000, 15000000), // Giá từ 2tr - 15tr
                        'capacity' => rand(2, 4),
                        'total_rooms' => rand(4, 20), // Số lượng phòng từ 4-20
                        'available_rooms' => rand(4, 20),
                        'area' => rand(20, 50),
                        'deck' => rand(1, 4),
                        'image_url' => '/images/cabin-' . rand(1, 5) . '.jpg'
                    ]);
                }
                
                DB::commit();
                $this->command->info("Đã tạo du thuyền: $name với $numCabins hạng phòng.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("Lỗi tạo tàu $name: " . $e->getMessage());
            }
        }
    }
}