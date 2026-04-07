<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        // 1. Xác thực dữ liệu gửi lên từ React
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'cruise_id' => 'required|exists:cruises,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000'
        ]);

        $userId = auth()->id();

        // 2. Bảo mật: Kiểm tra xem Booking này có đúng là của User đang đăng nhập không
        // Đồng thời phải đảm bảo trạng thái đã "completed" mới cho đánh giá
        $booking = Booking::where('id', $request->booking_id)
                          ->where('user_id', $userId)
                          ->first();

        if (!$booking) {
            return response()->json(['message' => 'Bạn không có quyền đánh giá đơn hàng này!'], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Chuyến đi chưa hoàn thành, không thể đánh giá!'], 400);
        }

        // 3. Kiểm tra xem đã đánh giá trước đó chưa (Chống Spam đánh giá 2 lần)
        $exists = Review::where('booking_id', $request->booking_id)->exists();
        if ($exists) {
            return response()->json(['message' => 'Bạn đã đánh giá chuyến đi này rồi!'], 400);
        }

        // 4. Lưu đánh giá vào Database
        $review = Review::create([
            'user_id' => $userId,
            'cruise_id' => $request->cruise_id,
            'booking_id' => $request->booking_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cảm ơn bạn đã gửi đánh giá!',
            'data' => $review
        ], 201);
    }
}