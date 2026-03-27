<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $guarded = [];

    // Lấy thông tin người đã viết đánh giá
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
