<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; 
use Illuminate\Database\Eloquent\SoftDeletes;
class Schedule extends Model
{
    use HasFactory , SoftDeletes;
    public function cruise() 
    {
    return $this->belongsTo(Cruise::class, 'cruise_id');
    }  
    public function cabin_classes() {
    return $this->belongsToMany(CabinClass::class, 'cabin_class_schedule')
                ->withPivot('available_rooms')
                ->withTimestamps();
}
    protected $guarded = [];
}
