<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'title', 'description', 
        'discount_amount', 'discount_percent', 
        'min_order_value', 'max_discount_amount',
        'usage_limit', 'used_count', 'is_active',
        'starts_at', 'expires_at'
    ];
}