<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public function cruise() 
    {
    return $this->belongsTo(Cruise::class, 'cruise_id');
    }  
    protected $guarded = [];
}
