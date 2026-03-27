<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingDetail extends Model
{
    
public function cabinClass()
    {
        return $this->belongsTo(CabinClass::class, 'cabin_class_id');
    }
    protected $guarded = [];
}
