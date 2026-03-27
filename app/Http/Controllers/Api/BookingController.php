<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\CabinClass;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Events\RoomReleased; // THÊM DÒNG NÀY ĐỂ MANG CÁI "LOA" VÀO

class BookingController extends Controller
{
    // ĐÂY CHÍNH LÀ HÀM MÀ LARAVEL ĐANG TÌM KIẾM
    public function holdRoom(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
            'cabin_class_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $cabin = CabinClass::where('id', $request->cabin_class_id)->lockForUpdate()->first();

            if (!$cabin || $cabin->available_rooms < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rất tiếc! Hạng phòng này vừa được người khác giữ chỗ hoặc đã hết.'
                ], 400);
            }

            // Trừ số lượng phòng trong DB
            $cabin->available_rooms -= $request->quantity;
            $cabin->save();

            $bookingCode = 'BK-' . strtoupper(Str::random(6));

            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'user_id' => auth()->id(),
                'schedule_id' => $request->schedule_id,
                'total_price' => $cabin->price * $request->quantity,
                'status' => 'holding',
                'hold_expires_at' => now()->addMinutes(15),
                'customer_name' => $request->customer_name ?? 'Khách Hàng',
                'customer_email' => $request->customer_email ?? 'khach@gmail.com',
            ]);

            BookingDetail::create([
                'booking_id' => $booking->id,
                'cabin_class_id' => $cabin->id,
                'quantity' => $request->quantity,
                'price' => $cabin->price
            ]);

            // Chốt giao dịch lưu vào DB
            DB::commit();

            // THÊM DÒNG NÀY: Ngay khi lưu thành công, cầm loa hét lên cho tất cả cùng biết!
            // React sẽ nghe thấy và tự động trừ đi số phòng trên màn hình
            broadcast(new RoomReleased($cabin->id, $cabin->available_rooms));

            return response()->json([
                'status' => 'success',
                'message' => 'Đã giữ phòng thành công!',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'hold_expires_at' => $booking->hold_expires_at
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
    public function myBookings()
{
    // Lấy tất cả đơn hàng của User đang đăng nhập, join kèm theo lịch trình, tàu, và chi tiết hạng phòng
    $bookings = Booking::with(['schedule.cruise.images', 'details.cabinClass'])
        ->where('user_id', auth()->id())
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'status' => 'success',
        'data' => $bookings
    ]);
}
}