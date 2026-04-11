<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CabinClass extends Model
{
    use HasFactory;

    public function images()
    {
        return $this->hasMany(CabinImage::class, 'cabin_class_id');
    }
    public function schedules() {
    return $this->belongsToMany(Schedule::class, 'cabin_class_schedule')
                ->withPivot('available_rooms')
                ->withTimestamps();
}
    // 2. Một phòng có nhiều tiện ích (Ban công, Bồn tắm, Minibar...)
    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'cabin_class_amenity', 'cabin_class_id', 'amenity_id');
    }
    protected $guarded = [];
    
}