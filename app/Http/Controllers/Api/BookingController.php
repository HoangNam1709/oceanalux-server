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

        $user = auth()->user();
        $now = now();
        $newSchedule = \App\Models\Schedule::find($request->schedule_id);
        $targetCruiseId = $newSchedule->cruise_id ?? null;
        // 1. TÌM ĐƠN HÀNG ĐANG GIỮ CHỖ BẤT KỲ CỦA USER NÀY
        $existingBooking = Booking::with(['schedule', 'details'])
            ->where('user_id', $user->id)
            ->where('status', 'holding')
            ->where('hold_expires_at', '>', $now)
            ->whereHas('schedule', function ($q) use ($targetCruiseId) { // <--- ĐÃ THÊM USE
                $q->where('cruise_id', $targetCruiseId);
            })
            ->first();

        if ($existingBooking) {
            $oldCabinId = $existingBooking->details->first()->cabin_class_id ?? null;

            // Kịch bản A: Khách bấm F5 hoặc chọn lại đúng phòng/ngày đang giữ
            $isSameBooking = ($existingBooking->schedule_id == $request->schedule_id) && ($oldCabinId == $request->cabin_class_id);

            if ($isSameBooking) {
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

            // Kịch bản B: Khách đang chọn phòng/ngày MỚI khác với đơn cũ
            // Nếu React có gửi cờ báo hiệu "TÔI MUỐN HỦY ĐƠN CŨ"
            if ($request->has('force_cancel_old') && $request->force_cancel_old == true) {
                $existingBooking->releaseRoom(); // Hàm này của bạn sẽ Hủy và trả lại phòng cho lịch trình cũ
            } else {
                // Nếu React chưa gửi cờ, báo về bắt React hiện Popup hỏi ý kiến khách
                return response()->json([
                    'status' => 'require_confirmation',
                    'message' => 'Bạn đang có một đơn hàng khác đang chờ thanh toán.',
                    'data' => [
                        'old_booking_id' => $existingBooking->id,
                        'old_schedule_id' => $existingBooking->schedule_id,
                        'old_cabin_id' => $oldCabinId,
                        // Lấy ngày đi để hiển thị lên popup
                        'old_date' => $existingBooking->schedule->departure_time ?? $existingBooking->schedule->departure_date ?? null, 
                    ]
                ]);
            }
        }

        // ============================================
        // 2. NẾU KHÔNG CÓ XUNG ĐỘT, TIẾN HÀNH TẠO MỚI
        // ============================================
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
            $capacity = $cabin->capacity ?? 2; // Tiêu chuẩn của phòng
            $guests = $request->guests ?? 2;   // Số khách gửi từ React lên
            
            // 1. Chặn đứng nếu hack gửi số lượng vượt quá +2
            if ($guests > $capacity + 2) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Số lượng khách vượt quá quy định của phòng. Vui lòng chọn phòng khác!'
                ], 400);
            }

            // 2. Tính toán giá mới (Phụ thu 15% nếu quá tiêu chuẩn)
            $priceMultiplier = 1;
            if ($guests > $capacity && $guests <= $capacity + 2) {
                $priceMultiplier = 1.15;
            }
            
            $finalCabinPrice = $cabin->price * $priceMultiplier;
            // ==========================================

            // Trừ số lượng phòng
            $cabin->available_rooms -= $request->quantity;
            $cabin->save();


            $bookingCode = 'BK-' . strtoupper(Str::random(6));
            $expiresAt = now()->addMinutes(15);

            $booking = Booking::create([
                'booking_code' => $bookingCode,
                'user_id' => $user->id,
                'schedule_id' => $request->schedule_id,
                'total_price' => $finalCabinPrice * $request->quantity,
                'status' => 'holding',
                'hold_expires_at' => $expiresAt,
                'customer_name' => $request->customer_name ?? $user->name,
                'customer_email' => $request->customer_email ?? $user->email,
                'customer_phone' => $request->customer_phone ?? $user->phone,
                'guests' => $guests,
            ]);

            BookingDetail::create([
                'booking_id' => $booking->id,
                'cabin_class_id' => $cabin->id,
                'quantity' => $request->quantity,
                'price' => $finalCabinPrice
            ]);

            DB::commit();

            // Kích hoạt Real-time nhả phòng 
            broadcast(new RoomReleased($cabin->id, $cabin->available_rooms, $request->schedule_id));

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
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống', 'error' => $e->getMessage()], 500);
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