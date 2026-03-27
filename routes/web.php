<?php

use Illuminate\Support\Facades\Route;

Route::get('/test-push/{id}', function($id) {
    $booking = App\Models\Booking::find($id);
    // Giả sử bạn cộng thêm 5 phút nữa
    $booking->update(['hold_expires_at' => now()->addMinutes(5)]);
    
    event(new App\Events\BookingUpdated($booking->id, $booking->hold_expires_at, $booking->status));
    return "Đã bắn tín hiệu cập nhật cho Booking " . $id;
});
