<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Thông tin đăng nhập không chính xác.'
            ], 401);
        }

        // Tạo Token mới cho phiên đăng nhập này
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
          'status' => 'success',
          'access_token' => $token,
          'token_type' => 'Bearer',
          'user' => [
              'name' => $user->name,
              'role' => $user->role
          ]
        ]);
    }
    public function register(Request $request)
   {
    // 1. Kiểm tra dữ liệu đầu vào
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed', // confirmed yêu cầu có thêm ô password_confirmation
        'phone' => 'nullable|string'
    ]);

    // 2. Tạo User mới
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password), // Mã hóa mật khẩu
        'role' => 'customer', // Mặc định đăng ký là khách hàng
        'phone' => $request->phone,
    ]);

    // 3. Tạo Token để khách đăng ký xong là đăng nhập luôn 
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status' => 'success',
        'message' => 'Đăng ký thành viên OceanaLux thành công!',
        'access_token' => $token,
        'token_type' => 'Bearer',
        'user' => [
            'name' => $user->name,
            'role' => $user->role
        ]
    ]);
}
    
}