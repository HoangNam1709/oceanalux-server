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
use App\Models\Schedule;

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
        $newSchedule = Schedule::find($request->schedule_id);
        
        if (!$newSchedule) {
             return response()->json(['status' => 'error', 'message' => 'Lịch trình không tồn tại.'], 404);
        }
        
        $targetCruiseId = $newSchedule->cruise_id;

        // ============================================
        // 1. TÌM ĐƠN HÀNG ĐANG GIỮ CHỖ CỦA USER NÀY
        // ============================================
        $existingBooking = Booking::with(['schedule', 'details'])
            ->where('user_id', $user->id)
            ->where('status', 'holding')
            ->where('hold_expires_at', '>', $now)
            ->whereHas('schedule', function ($q) use ($targetCruiseId) { 
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

            // Kịch bản B: Khách đang chọn phòng/ngày MỚI
            if ($request->has('force_cancel_old') && $request->force_cancel_old == true) {
                // Đảm bảo hàm releaseRoom() trong Booking.php đã được sửa để cộng lại phòng vào Pivot
                $existingBooking->releaseRoom(); 
            } else {
                return response()->json([
                    'status' => 'require_confirmation',
                    'message' => 'Bạn đang có một đơn hàng khác đang chờ thanh toán.',
                    'data' => [
                        'old_booking_id' => $existingBooking->id,
                        'old_schedule_id' => $existingBooking->schedule_id,
                        'old_cabin_id' => $oldCabinId,
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

            // 🚨 BƯỚC QUAN TRỌNG NHẤT: Tìm số lượng phòng từ bảng Pivot và Lock nó lại
            $schedule = Schedule::with(['cabin_classes' => function($q) use ($request) {
                // Khóa row trong bảng pivot để tránh 2 người book cùng lúc
                $q->where('cabin_class_id', $request->cabin_class_id);
            }])->lockForUpdate()->findOrFail($request->schedule_id);

            $cabin = $schedule->cabin_classes->first();

            // Nếu không tìm thấy liên kết giữa ngày và phòng, hoặc số lượng phòng không đủ
            if (!$cabin || $cabin->pivot->available_rooms < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rất tiếc! Hạng phòng này vừa được người khác giữ chỗ hoặc đã hết.'
                ], 400); 
            }

            $capacity = $cabin->capacity ?? 2; 
            $guests = $request->guests ?? 2;  
            
            // 1. Chặn đứng nếu hack gửi số lượng vượt quá +2
            if ($guests > $capacity + 2) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Số lượng khách vượt quá quy định của phòng. Vui lòng chọn phòng khác!'
                ], 400);
            }

            // 2. Tính toán giá mới (Phụ thu 15% nếu quá tiêu chuẩn)
            $priceMultiplier = ($guests > $capacity && $guests <= $capacity + 2) ? 1.15 : 1;
            $finalCabinPrice = $cabin->price * $priceMultiplier;

            $newAvailableCount = $cabin->pivot->available_rooms - $request->quantity;
            $schedule->cabin_classes()->updateExistingPivot($cabin->id, [
                'available_rooms' => $newAvailableCount
            ]);

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

            // 🚨 PHÁT SỰ KIỆN: Truyền ĐÚNG ID phòng, số lượng MỚI, và ID lịch trình
            broadcast(new RoomReleased($cabin->id, $request->schedule_id, $newAvailableCount));

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
        $booking = Booking::where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng hoặc bạn không có quyền!'], 404);
        }

        if ($booking->status !== 'holding') {
            return response()->json(['message' => 'Chỉ có thể hủy đơn hàng đang chờ thanh toán.'], 400);
        }

        // Đảm bảo Model Booking có hàm releaseRoom() xử lý hoàn phòng vào bảng Pivot
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
        $booking = Booking::with(['schedule.cruise.images', 'details.cabinClass'])
            ->where('id', $id)
            ->where('user_id', auth()->id()) 
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng hoặc bạn không có quyền truy cập'], 404);
        }

        if ($booking->hold_expires_at) {
            $remainingSeconds = now()->diffInSeconds($booking->hold_expires_at, false);
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