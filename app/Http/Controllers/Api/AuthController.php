<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Otp;
use App\Mail\SendOTPMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    /* =========================================================
     * 1. XÁC THỰC CƠ BẢN (LOGIN / LOGOUT)
     * ========================================================= */

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thông tin đăng nhập không chính xác.'
            ], 401);
        }

        // Xóa token cũ để đảm bảo 1 tài khoản chỉ có 1 phiên đăng nhập hợp lệ
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng xuất thành công, Token đã được hủy!'
        ]);
    }


    /* =========================================================
     * 2. LUỒNG BẢO MẬT OTP (ĐĂNG KÝ & QUÊN MẬT KHẨU)
     * ========================================================= */

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'type'  => 'required|in:register,reset_password'
        ]);

        $email = $request->email;
        $type = $request->type;
        $key = 'send-otp:' . $email;

        // Chống Spam (1 phút / 1 lần)
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Hành động quá nhanh! Vui lòng thử lại sau {$seconds} giây."
            ], 429);
        }

        // Kiểm tra điều kiện tồn tại của Email
        $userExists = User::where('email', $email)->exists();
        if ($type === 'register' && $userExists) {
            return response()->json(['message' => 'Email này đã tồn tại trong hệ thống!'], 400);
        }
        if ($type === 'reset_password' && !$userExists) {
            return response()->json(['message' => 'Email không tồn tại trong hệ thống!'], 400);
        }

        // Tạo và lưu mã OTP (Ghi đè mã cũ nếu có)
        $otpCode = rand(100000, 999999);
        Otp::updateOrCreate(
            ['email' => $email, 'type' => $type],
            ['otp' => $otpCode, 'expires_at' => now()->addMinutes(5)]
        );

        // Đẩy tiến trình gửi Mail vào Queue và thiết lập Rate Limit
        Mail::to($email)->queue(new SendOTPMail($otpCode));
        RateLimiter::hit($key, 60);

        return response()->json(['message' => 'Mã xác thực đã được gửi tới hòm thư của bạn.']);
    }

    public function verifyAndProcess(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => 'required|min:6|confirmed',
            'type' => 'required|in:register,reset_password',
            'name' => 'nullable|string|max:255', 
            'phone' => 'nullable|string|max:20'  
        ]);

        // Xác minh OTP có hợp lệ và còn hạn không
        $otpRecord = Otp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('type', $request->type)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Mã OTP không đúng hoặc đã hết hạn.'], 400);
        }

        // Thực thi Logic cốt lõi
        if ($request->type === 'reset_password') {
            User::where('email', $request->email)->update([
                'password' => Hash::make($request->password)
            ]);
        } else {
            User::create([
                'name' => $request->name ?? explode('@', $request->email)[0],
                'email' => $request->email,
                'phone' => $request->phone, 
                'role' => 'customer',
                'password' => Hash::make($request->password),
            ]);
        }

        // Xóa mã OTP sau khi sử dụng thành công để bảo mật
        $otpRecord->delete();

        return response()->json(['message' => 'Thao tác thành công!']);
    }


    /* =========================================================
     * 3. QUẢN LÝ TÀI KHOẢN (PROFILE)
     * ========================================================= */

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $request->user(); 
        $user->update($request->only(['name', 'phone']));

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin thành công!',
            'user' => $user
        ]);
    }
}