<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ĐĂNG NHẬP
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thông tin đăng nhập không chính xác.'
            ], 401);
        }

        // TỐI ƯU: Xóa các token cũ đi để tránh rác Database (1 tài khoản 1 phiên đăng nhập)
        $user->tokens()->delete();

        // Tạo Token mới
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user // TỐI ƯU: Trả về toàn bộ object user để React dùng luôn
        ]);
    }

    /**
     * ĐĂNG KÝ
     */
    public function register(Request $request)
    {
        // 1. Kiểm tra dữ liệu đầu vào
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', 
            'phone' => 'nullable|string|max:20' // TỐI ƯU: Giới hạn độ dài số điện thoại
        ]);

        // 2. Tạo User mới
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), 
            'role' => 'customer', 
            'phone' => $request->phone,
        ]);

        // 3. Tạo Token để khách đăng ký xong là đăng nhập luôn 
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng ký thành viên OceanaLux thành công!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user // TỐI ƯU: Trả về toàn bộ thông tin
        ]);
    }

    /**
     * CẬP NHẬT THÔNG TIN CÁ NHÂN
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); 
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        // Cập nhật vào DB
        $user->update([
            'name' => $request->name,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thông tin thành công!',
            'user' => $user
        ]);
    }

    /**
     * 👉 TÍNH NĂNG MỚI: ĐĂNG XUẤT (CỰC KỲ QUAN TRỌNG)
     * Hủy token trong Database để bảo mật an toàn tuyệt đối
     */
    public function logout(Request $request)
    {
        // Thu hồi (xóa) token hiện tại của người dùng
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Đăng xuất thành công, Token đã được hủy!'
        ]);
    }
}