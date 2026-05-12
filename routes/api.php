<?php

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CruiseController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AdminController; 
use App\Http\Controllers\Api\CouponController;
// Test gửi Mail
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingSuccessMail;

Route::get('/test-mail', function () {
    $booking = Booking::with(['schedule.cruise', 'details.cabinClass'])->first();
    Mail::to('hoangnam170924@gmail.com')->send(new BookingSuccessMail($booking));
    return "Xong! Kiểm tra hòm thư của bạn đi.";
});

// 1. CÁC API PUBLIC (KHÔNG CẦN ĐĂNG NHẬP)

// --- XÁC THỰC (AUTH) ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-and-process', [AuthController::class, 'verifyAndProcess']);

// --- DU THUYỀN & TÌM KIẾM ---
Route::get('/cruises', [CruiseController::class, 'index']);
Route::get('/cruises/{id}', [CruiseController::class, 'show']);
Route::get('/schedules/{id}/available-cabins', [CruiseController::class, 'getAvailableCabins']);
Route::get('/coupons', [CouponController::class, 'index']);

// --- THANH TOÁN VNPAY (WEBHOOK/VERIFY) ---
// Gọi ngầm từ Server VNPay
Route::get('/payment/vnpay-ipn', [PaymentController::class, 'vnpayIpn']);
// Gọi từ Frontend React sau khi VNPAY chuyển hướng về
Route::get('/payment/verify', [PaymentController::class, 'verifyPayment']);

// 2. CÁC API BẢO MẬT (YÊU CẦU PHẢI CÓ TOKEN SANCTUM)
Route::middleware('auth:sanctum')->group(function () {

    // --- TÀI KHOẢN USER ---
    Route::get('/user', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    });
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']); 
    
    // --- QUẢN LÝ ĐẶT PHÒNG (GÓC ĐỘ KHÁCH HÀNG) ---
    Route::get('/my-bookings', [BookingController::class, 'myBookings']); 
    Route::get('/bookings/{id}', [BookingController::class, 'show']); 
    Route::post('/bookings/hold', [BookingController::class, 'holdRoom']);
    
    // Hủy đơn chưa thanh toán (Holding)
    Route::post('/bookings/{id}/cancel-holding', [BookingController::class, 'cancelHoldingBooking']); 
    // Hủy đơn đã thanh toán & Yêu cầu hoàn tiền (Paid)
    Route::post('/bookings/{id}/request-refund', [BookingController::class, 'requestRefundBooking']);  
    // --- KHỞI TẠO THANH TOÁN ---
    Route::post('/payment/create', [PaymentController::class, 'createPayment']);
    // --- ĐÁNH GIÁ (REVIEW) ---
    Route::post('/reviews', [ReviewController::class, 'store']);

    // 3. KHU VỰC CỦA QUẢN TRỊ VIÊN (YÊU CẦU ROLE = ADMIN - NẾU CÓ MIDDLEWARE)
    Route::prefix('admin')->group(function () {
        
        // --- THỐNG KÊ DASHBOARD ---
        Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
        Route::get('/dashboard/schedules-health', [AdminController::class, 'getSchedulesHealth']);
        Route::get('/overview/stats', [AdminController::class, 'getOverviewStats']);
        
        // --- THỐNG KÊ DOANH THU ---
        Route::get('/revenue/stats', [AdminController::class, 'getRevenueStats']);
        Route::get('/revenue/export', [AdminController::class, 'exportRevenueExcel']);

        // --- QUẢN LÝ ĐƠN HÀNG (BOOKING) ---
        Route::get('/bookings', [AdminController::class, 'getBookings']);
        Route::put('/bookings/{id}/status', [AdminController::class, 'updateBookingStatus']);
        Route::post('/bookings/{id}/cancel-refund', [AdminController::class, 'cancelAndRefundBooking']);
        Route::post('/bookings/{id}/process-refund', [AdminController::class, 'processRefund']);
        // --- QUẢN LÝ DU THUYỀN (CRUISE) ---
        Route::get('/cruises', [AdminController::class, 'getCruises']);
        Route::post('/cruises', [AdminController::class, 'storeCruise']);
        Route::put('/cruises/{id}', [AdminController::class, 'updateCruise']);
        Route::delete('/cruises/{id}', [AdminController::class, 'deleteCruise']);
        Route::get('/amenities', [AdminController::class, 'getAllAmenities']);
        // --- QUẢN LÝ HẠNG PHÒNG (CABIN) ---
        Route::post('/cabins', [AdminController::class, 'storeCabin']);       
        Route::put('/cabins/{id}', [AdminController::class, 'updateCabin']);  
        Route::delete('/cabins/{id}', [AdminController::class, 'deleteCabin']); 
        
        // --- QUẢN LÝ LỊCH TRÌNH (SCHEDULE) ---
        Route::get('/schedules', [AdminController::class, 'getSchedules']);
        Route::post('/schedules', [AdminController::class, 'storeSchedule']);
        Route::put('/schedules/{id}', [AdminController::class, 'updateSchedule']);
        Route::delete('/schedules/{id}', [AdminController::class, 'deleteSchedule']);
        
        // --- QUẢN LÝ TÀI KHOẢN (ACCOUNT) ---
        Route::get('/accounts', [AdminController::class, 'getAccounts']);
        Route::post('/accounts', [AdminController::class, 'storeAccount']);
        Route::put('/accounts/{id}', [AdminController::class, 'updateAccount']);
        Route::delete('/accounts/{id}', [AdminController::class, 'deleteAccount']);

        //--- QUẢN LÝ ẢNH DU THUYỀN & HẠNG PHÒNG ---
        Route::post('/{type}/{id}/images', [AdminController::class, 'addGalleryImage']);
        Route::delete('/{type}/images/{imageId}', [AdminController::class, 'deleteGalleryImage']);
        Route::patch('/{type}/{id}/set-thumbnail', [AdminController::class, 'setAsThumbnail']);
        
    });
});