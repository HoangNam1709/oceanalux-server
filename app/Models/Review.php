<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $guarded = [];

    public function images()
    {
        return $this->hasMany(ReviewImage::class, 'review_id');
    }
    
    // Liên kết tới user (người đánh giá)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}