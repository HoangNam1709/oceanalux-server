<?php

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\Cruise;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItineraryFactory extends Factory
{
    protected $model = Itinerary::class;

    public function definition()
    {
        return [
            // Các giá trị này sẽ bị Seeder ghi đè để đảm bảo logic, nhưng cứ viết dự phòng ở đây
            'cruise_id' => Cruise::inRandomOrder()->first()->id ?? 1,
            'day_number' => 1,
            'location' => $this->faker->city(),
            'description' => $this->faker->paragraph(),
            'activities' => json_encode(['Nghỉ ngơi', 'Ngắm cảnh']),
        ];
    }
}