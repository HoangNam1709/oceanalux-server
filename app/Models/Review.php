<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Review extends Model
{
    use HasFactory;
    protected $guarded = [];

    // Lấy thông tin người đã viết đánh giá
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
