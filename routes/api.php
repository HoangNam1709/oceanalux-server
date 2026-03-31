<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CruiseController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuthController;
// Khi ReactJS gọi tới /api/cruises, nó sẽ chạy vào hàm index của CruiseController
Route::get('/cruises', [CruiseController::class, 'index']);
Route::get('/cruises/{id}', [CruiseController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings/hold', [BookingController::class, 'holdRoom']);
});
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->group(function () {
    // 1. Route lấy thông tin User (Mặc định Laravel đã có)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 2. BẠN CẦN TẠO ROUTE NÀY: Trả về các Booking của user hiện tại
    Route::get('/my-bookings', [BookingController::class, 'myBookings']); 
});
Route::get('/bookings/{id}', [BookingController::class, 'show']);

// Đảm bảo route nằm trong group api hoặc định nghĩa trực tiếp như sau:
Route::post('/payment/create', [PaymentController::class, 'createPayment']);