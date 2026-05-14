<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'default_multiplier', 'is_active', 'description'];

    // Một ngày lễ có thể được áp dụng cho nhiều lịch trình
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}