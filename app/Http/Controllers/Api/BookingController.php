<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\CabinClass;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Events\RoomReleased; 

class BookingController extends Controller
{
    /**
     * GIỮ PHÒNG / TẠO ĐƠN HÀNG MỚI
     */
    public function holdRoom(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
            'cabin_class_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        // 👉 TỐI ƯU: Lấy toàn bộ thông tin User đang đăng nhập thay vì chỉ lấy ID
        $user = auth()->user();
        $now = now();

        // 1. CHỐT CHẶN: Kiểm tra xem User này đã có đơn hàng 'holding' còn hạn không
        $existingBooking = Booking::where('user_id', $user->id)
            ->where('status', 'holding')
            ->where('hold_expires_at', '>', $now)
            ->whereHas('details', function ($q) use ($request) {
                $q->where('cabin_class_id', $request->cabin_class_id);
            })
            ->first();

        if ($existingBooking) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tiếp tục đơn hàng đang chờ thanh toán.',
                'data' => [
                    'booking_id' => $existingBooking->id,
                    'booking_code' => $existingBooking->booking_code,
                    'hold_expires_at' => $existingBooking->hold_expires_at,
                    'remaining_seconds' => (int) $now->diffInSeconds($existingBooking->hold_expires_at, false)
                ]
            ]);
        }

        // 2. NẾU KHÔNG CÓ, TIẾN HÀNH TẠO MỚI TRONG TRANSACTION
        try {
            DB::beginTransaction();

            // Khóa dòng dữ liệu để chống Overbooking (Race Condition)
            $cabin = CabinClass::where('id', $request->cabin_class_id)->lockForUpdate()->first();

            // KIỂM TRA PHÒNG TRỐNG (Đưa vào đúng vị trí sau khi lock)
            if (!$cabin || $cabin->available_rooms < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rất tiếc! Hạng phòng này vừa được người khác giữ chỗ hoặc đã hết.'
                ], 400); // 400 Bad Request
            }

            // Trừ số lượng phòng
            $cabin->available_rooms -= $request->quantity;
            $cabin->save();

            // Tạo mã booking ngẫu nhiên
            $bookingCode = 'BK-' . strtoupper(Str::random(6));
            
            // Thiết lập thời gian hết hạn (15 phút)
            $expiresAt = now()->addMinutes(15);

            // Tạo đơn hàng
            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'user_id' => $user->id,
                'schedule_id' => $request->schedule_id,
                'total_price' => $cabin->price * $request->quantity,
                'status' => 'holding',
                'hold_expires_at' => $expiresAt,
                
                // 👉 TỐI ƯU: Lấy dữ liệu từ DB, nếu Request có truyền lên thì ưu tiên Request
                'customer_name' => $request->customer_name ?? $user->name,
                'customer_email' => $request->customer_email ?? $user->email,
                'customer_phone' => $request->customer_phone ?? $user->phone,
            ]);

            // Tạo chi tiết đơn hàng
            BookingDetail::create([
                'booking_id' => $booking->id,
                'cabin_class_id' => $cabin->id,
                'quantity' => $request->quantity,
                'price' => $cabin->price
            ]);

            DB::commit();

            // Phát event (Nếu bạn dùng Socket)
            broadcast(new RoomReleased($cabin->id, $cabin->available_rooms));

            return response()->json([
                'status' => 'success',
                'message' => 'Đã giữ phòng thành công!',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'hold_expires_at' => $booking->hold_expires_at,
                    'remaining_seconds' => (int) now()->diffInSeconds($booking->hold_expires_at, false)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống khi giữ phòng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * KHÁCH HÀNG TỰ HỦY ĐƠN
     */
    public function cancelBooking($id)
    {
        // 1. Tìm đơn hàng (phải đúng là của user đang đăng nhập)
        $booking = Booking::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng hoặc bạn không có quyền!'], 404);
        }

        // 2. Chỉ cho phép hủy nếu đơn đang 'holding'
        if ($booking->status !== 'holding') {
            return response()->json(['message' => 'Chỉ có thể hủy đơn hàng đang chờ thanh toán.'], 400);
        }

        // 3. Gọi hàm releaseRoom() ở Model để xử lý nhả phòng an toàn
        $success = $booking->releaseRoom();

        if ($success) {
            return response()->json([
                'status' => 'success', 
                'message' => 'Đã hủy đơn hàng và giải phóng phòng thành công!'
            ]);
        }

        return response()->json([
            'status' => 'error', 
            'message' => 'Có lỗi xảy ra khi hủy đơn.'
        ], 500);
    }
    /**
     * LẤY DANH SÁCH ĐƠN HÀNG CỦA USER ĐANG ĐĂNG NHẬP
     */
    public function myBookings()
    {
        $bookings = Booking::with(['schedule.cruise.images', 'details.cabinClass'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bookings
        ]);
    }

    /**
     * LẤY CHI TIẾT 1 ĐƠN HÀNG 
     */
    public function show($id)
    {
        // Phải bắt buộc thêm điều kiện bảo mật: Chỉ cho phép lấy đơn của chính User đó
        $booking = Booking::with(['schedule.cruise.images', 'details.cabinClass'])
            ->where('id', $id)
            ->where('user_id', auth()->id()) // BẢO MẬT: Chống hacker dò ID người khác
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập'], 404);
        }

        // TÍNH TOÁN LẠI ĐỒNG HỒ DỰA TRÊN HOLD_EXPIRES_AT
        if ($booking->hold_expires_at) {
            $remainingSeconds = now()->diffInSeconds($booking->hold_expires_at, false);
            // Nếu đã quá hạn (số âm), ép về 0
            $booking->remaining_seconds = $remainingSeconds > 0 ? (int) $remainingSeconds : 0;
        } else {
            $booking->remaining_seconds = 0;
        }

        return response()->json([
            'status' => 'success',
            'data' => $booking
        ]);
    }
}