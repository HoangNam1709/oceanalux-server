<?php

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CruiseController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController; 
use App\Http\Controllers\Api\ReviewController;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingSuccessMail;


//CÁC ROUTE PUBLIC (KHÔNG CẦN ĐĂNG NHẬP)

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-and-process', [AuthController::class, 'verifyAndProcess']);
Route::get('/cruises', [CruiseController::class, 'index']);
Route::get('/cruises/{id}', [CruiseController::class, 'show']);
Route::get('/schedules/{id}/available-cabins', [CruiseController::class, 'getAvailableCabins']);
// VNPAY IPN (Bắt buộc phải Public để Server VNPay có thể gửi kết quả về)
Route::get('/payment/vnpay-ipn', [PaymentController::class, 'vnpayIpn']);

// Test gửi Mail
Route::get('/test-mail', function () {
    $booking = Booking::with(['schedule.cruise', 'details.cabinClass'])->first();
    Mail::to('hoangnam170924@gmail.com')->send(new BookingSuccessMail($booking));
    return "Xong! Kiểm tra hòm thư của bạn đi.";
});

// CÁC ROUTE PROTECTED (BẮT BUỘC ĐĂNG NHẬP BẰNG TOKEN)

Route::middleware('auth:sanctum')->group(function () {

    // KHU VỰC CỦA NGƯỜI DÙNG (USER)
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    });
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']); // Route Đăng xuất an toàn
    
    // --- Quản lý Đặt phòng của khách ---
    Route::get('/my-bookings', [BookingController::class, 'myBookings']); 
    Route::get('/bookings/{id}', [BookingController::class, 'show']); 
    Route::post('/bookings/hold', [BookingController::class, 'holdRoom']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']); // Khách tự hủy đơn
    
    // --- Thanh toán ---
    Route::post('/payment/create', [PaymentController::class, 'createPayment']);
    Route::post('/reviews', [ReviewController::class, 'store']);


    // KHU VỰC CỦA QUẢN TRỊ VIÊN (ADMIN)
    Route::prefix('admin')->group(function () {
        // Lấy thống kê tổng quan (Dashboard)
        Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
        // Cập nhật trạng thái đơn hàng (Admin xử lý đơn)
        Route::put('/bookings/{id}/status', [AdminController::class, 'updateBookingStatus']);
        // Lấy danh sách toàn bộ đơn đặt vé
        Route::get('/bookings', [AdminController::class, 'getBookings']);
        
        // Lấy danh sách Du thuyền kèm theo Phòng (ĐÃ ĐƯỢC CHUYỂN VÀO ĐÚNG NHÀ)
        Route::get('/cruises', [AdminController::class, 'getCruises']);
        Route::post('/cruises', [AdminController::class, 'storeCruise']);
        Route::put('/cruises/{id}', [AdminController::class, 'updateCruise']); // Cập nhật
        Route::delete('/cruises/{id}', [AdminController::class, 'deleteCruise']); // Xóa
        Route::post('/cabins', [AdminController::class, 'storeCabin']);       // Thêm mới
        Route::put('/cabins/{id}', [AdminController::class, 'updateCabin']);  // Cập nhật
        Route::delete('/cabins/{id}', [AdminController::class, 'deleteCabin']); // Xóa
        Route::get('/schedules', [AdminController::class, 'getSchedules']);
        Route::post('/schedules', [AdminController::class, 'storeSchedule']);
        Route::put('/schedules/{id}', [AdminController::class, 'updateSchedule']);
        Route::delete('/schedules/{id}', [AdminController::class, 'deleteSchedule']);
        Route::get('/dashboard/schedules-health', [AdminController::class, 'getSchedulesHealth']);
        Route::get('/revenue/export', [AdminController::class, 'exportRevenueExcel']);
        Route::get('/revenue/stats', [AdminController::class, 'getRevenueStats']);
        Route::get('/overview/stats', [AdminController::class, 'getOverviewStats']);
        Route::get('/accounts', [AdminController::class, 'getAccounts']);
        Route::post('/accounts', [AdminController::class, 'storeAccount']);
        Route::put('/accounts/{id}', [AdminController::class, 'updateAccount']);
        Route::delete('/accounts/{id}', [AdminController::class, 'deleteAccount']);
    });
    
});