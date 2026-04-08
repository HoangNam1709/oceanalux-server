<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
Route::get('/test-push/{id}', function($id) {
    $booking = App\Models\Booking::find($id);
    // Giả sử bạn cộng thêm 5 phút nữa
    $booking->update(['hold_expires_at' => now()->addMinutes(5)]);
    
    event(new App\Events\BookingUpdated($booking->id, $booking->hold_expires_at, $booking->status));
    return "Đã bắn tín hiệu cập nhật cho Booking " . $id;
});


// 1. Nút bấm thanh toán gọi vào đây để lấy Link
Route::post('/payment/vnpay/{booking_id}', [PaymentController::class, 'createPayment']);

// 2. IPN: Nơi VNPay gọi ngầm về server của bạn (Nhớ loại bỏ CSRF cho route này nếu dùng web.php)
Route::get('/payment/vnpay/ipn', [PaymentController::class, 'vnpayIpn']);

// 3. Return URL: Nơi khách hàng được chuyển hướng về sau khi thanh toán trên app ngân hàng
Route::get('/payment/vnpay/return', [PaymentController::class, 'vnpayReturn']);
Route::get('/preview-otp', function () {
    return new \App\Mail\SendOTPMail('123456');
});