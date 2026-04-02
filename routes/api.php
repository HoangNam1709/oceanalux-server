<?php

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CruiseController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuthController;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingSuccessMail;
/*
|--------------------------------------------------------------------------
| CÁC ROUTE PUBLIC (KHÔNG CẦN ĐĂNG NHẬP)
|--------------------------------------------------------------------------
| Ai cũng có thể truy cập để xem tàu, đăng ký, đăng nhập và Webhook VNPAY
*/
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/cruises', [CruiseController::class, 'index']);
Route::get('/cruises/{id}', [CruiseController::class, 'show']);

// VNPAY IPN (Bắt buộc phải Public để Server VNPay có thể gửi kết quả về)
Route::get('/payment/vnpay-ipn', [PaymentController::class, 'vnpayIpn']);
Route::get('/test-mail', function () {
    // Lấy đại 1 đơn hàng trong DB để test template
    $booking = Booking::with(['schedule.cruise', 'details.cabinClass'])->first();
    
    // Gửi đến email thật của bạn (điền email bạn hay dùng để nhận thư)
    Mail::to('hoangnam170924@gmail.com')->send(new BookingSuccessMail($booking));
    
    return "Xong! Kiểm tra hòm thư của bạn đi.";
});
/*
|--------------------------------------------------------------------------
| CÁC ROUTE PROTECTED (BẮT BUỘC ĐĂNG NHẬP BẰNG TOKEN)
|--------------------------------------------------------------------------
| Phải có Token hợp lệ trên Header mới được phép đi qua cửa này
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // --- QUẢN LÝ TÀI KHOẢN ---
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    });
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']); // Route Đăng xuất an toàn

    // --- QUẢN LÝ ĐẶT PHÒNG ---
    Route::get('/my-bookings', [BookingController::class, 'myBookings']); 
    
    // Đã chuyển vào khu vực bảo mật: Chỉ lấy được đơn hàng của chính mình
    Route::get('/bookings/{id}', [BookingController::class, 'show']); 
    
    Route::post('/bookings/hold', [BookingController::class, 'holdRoom']);

    // --- THANH TOÁN ---
    // Đã chuyển vào khu vực bảo mật: Chỉ user đang đăng nhập mới được tạo thanh toán
    Route::post('/payment/create', [PaymentController::class, 'createPayment']);
    // API Khách tự hủy đơn
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);
    
});