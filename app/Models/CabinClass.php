<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CabinClass extends Model
{
    use HasFactory;

    // Tắt khiên bảo vệ để cho phép bơm dữ liệu
    protected $guarded = [];
    
}