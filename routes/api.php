<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CruiseController;
use App\Http\Controllers\Api\BookingController;
// Khi ReactJS gọi tới /api/cruises, nó sẽ chạy vào hàm index của CruiseController
Route::get('/cruises', [CruiseController::class, 'index']);
Route::get('/cruises/{id}', [CruiseController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings/hold', [BookingController::class, 'holdRoom']);
});
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);