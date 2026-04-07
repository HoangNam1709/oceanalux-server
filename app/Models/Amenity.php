<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory; // <-- Đặt nó vào BÊN TRONG Model

    protected $fillable = ['name'];
}
