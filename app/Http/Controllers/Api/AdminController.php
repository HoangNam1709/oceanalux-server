<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\CabinClass;
use Carbon\Carbon;

class AdminController extends Controller
{

   /**
     * API 1: Lấy số liệu thống kê Tổng quan (Overview Stats)
     */
    public function getDashboardStats()
    {
        try {
            $totalBookings = Booking::count();
            
            // SỬA: Phải đếm cả đơn confirmed, paid và completed
            $confirmedBookings = Booking::whereIn('status', ['paid', 'completed'])->count();
        
            // SỬA: Doanh thu cũng phải tính tổng của cả 3 trạng thái này
            $totalRevenue = Booking::whereIn('status', [ 'paid', 'completed'])->sum('total_price');
            
            $totalGuests = $confirmedBookings * 2;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totalBookings' => $totalBookings,
                    'confirmedBookings' => $confirmedBookings,
                    'totalRevenue' => $totalRevenue,
                    'totalGuests' => $totalGuests,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi sập Server: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * API 2: Lấy danh sách Đặt vé (Bookings) cho bảng quản lý
     */
    public function getBookings()
    {
        $bookings = Booking::with(['schedule.cruise', 'details.cabinClass'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedBookings = $bookings->map(function ($booking) {
            

            $departureDate = $booking->schedule ? Carbon::parse($booking->schedule->departure_time)->format('d/m/Y') : 'N/A';
            $returnDate = $booking->schedule ? Carbon::parse($booking->schedule->departure_time)->addDays(2)->format('d/m/Y') : 'N/A';
            $bookedDate = $booking->created_at ? $booking->created_at->format('d/m/Y') : 'N/A';

            return [
                'id' => (string) $booking->id,
                'bookingRef' => $booking->booking_code,
                'guestName' => $booking->customer_name,
                'guestEmail' => $booking->customer_email,
                'cruiseName' => $booking->schedule->cruise->name ?? 'N/A',
                'departureDate' => $departureDate,
                'returnDate' => $returnDate,
                'cabinType' => $booking->details->first()->cabinClass->name ?? 'N/A',
                'guests' => $booking->guests ?? 2,
                'totalAmount' => (float) $booking->total_price,
                
                // SỬA: Lấy nguyên gốc trạng thái từ Database truyền sang React
                'status' => $booking->status,
                
                'paymentMethod' => strtoupper($booking->payment_method ?? 'CASH'),
                'bookedDate' => $bookedDate,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedBookings
        ]);
    }
    /**
     * API 3: Lấy danh sách Du thuyền kèm theo Phòng, Ảnh và Tiện ích
     */
    public function getCruises()
    {
        // Gọi dữ liệu Tàu kèm theo các bảng liên quan để tránh lỗi N+1 Query
        $cruises = Cruise::with(['cabinClasses', 'images', 'amenities'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedCruises = $cruises->map(function ($cruise) {
            
            // 1. Tính giá khởi điểm: Lấy giá của hạng phòng rẻ nhất
            $basePrice = $cruise->cabinClasses->min('price') ?? 0;

            // 2. Gom mảng Ảnh từ bảng cruise_images (Giả sử cột chứa link ảnh tên là 'image_url')
            $images = $cruise->images->pluck('image_url')->toArray();

            // 3. Gom mảng Tiện ích từ bảng cruise_amenities (Giả sử cột chứa tên tiện ích là 'name')
            $facilities = $cruise->amenities->pluck('name')->toArray();

            return [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                // Các trường React cần nhưng Database chưa thiết kế -> Dùng giá trị mặc định
                'destination' => 'Vịnh Hạ Long', 
                'durationDays' => 3,             
                'durationNights' => 2,           
                
                'starRating' => (float) ($cruise->star_rating ?? 5),
                'basePrice' => (float) $basePrice, // Đã tính toán tự động ở trên
                'images' => $images,
                'description' => $cruise->description ?? '',
                'facilities' => $facilities,
                'featured' => $cruise->status === 'active', // Trạng thái active sẽ lên top nổi bật
                
                // Map danh sách hạng phòng từ bảng cabin_classes
                'cabins' => $cruise->cabinClasses->map(function ($cabin) {
                    return [
                        'id' => (string) $cabin->id,
                        'type' => 'Ocean View', // Mặc định vì DB chưa có loại phòng (Suite, Balcony...)
                        'name' => $cabin->name,
                        'pricePerNight' => (float) $cabin->price,
                        'capacity' => (int) $cabin->capacity,
                        'available' => (int) $cabin->available_rooms,
                        'amenities' => [], // Để trống vì DB chưa có bảng tiện ích riêng cho từng hạng phòng
                        'imageUrl' => $cabin->image_url ?? '',
                    ];
                })->values()->toArray()
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedCruises
        ]);
    }
    /**
     * API 4: Thêm Du thuyền mới vào Database
     */
    public function storeCruise(Request $request)
    {
        try {
            // 1. Lưu dữ liệu vào DB (Đã thêm 3 trường mới)
            $cruise = new Cruise();
            $cruise->name = $request->name;
            $cruise->thumbnail = $request->thumbnail;
            $cruise->destination = $request->destination ?? 'Đang cập nhật'; // Điểm đến
            $cruise->duration_days = $request->durationDays ?? 3;            // Số ngày
            $cruise->duration_nights = $request->durationNights ?? 2;        // Số đêm
            $cruise->description = $request->description;
            $cruise->star_rating = $request->starRating ?? 5;
            $cruise->status = $request->status ?? 'active';
            $cruise->save();

            // 2. Format dữ liệu TRẢ VỀ ĐÚNG CHUẨN ĐỂ REACT KHÔNG BỊ TRẮNG MÀN HÌNH
            $newCruise = [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                'destination' => $cruise->destination,
                'durationDays' => (int) $cruise->duration_days,
                'durationNights' => (int) $cruise->duration_nights,
                'starRating' => (float) $cruise->star_rating,
                'basePrice' => 0,   // Giá mặc định bằng 0 vì chưa có phòng
                'images' => [],     // Bắt buộc phải là mảng rỗng để UI không bị lỗi images[0]
                'description' => $cruise->description,
                'facilities' => [], // Bắt buộc phải là mảng rỗng để không bị lỗi map()
                'featured' => $cruise->status === 'active',
                'cabins' => []      // Tàu mới tinh chưa có phòng
            ];

            return response()->json([
                'status' => 'success',
                'data' => $newCruise
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Lỗi DB: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * API 5: Cập nhật thông tin Du thuyền
     */
    public function updateCruise(Request $request, $id)
    {
        try {
            $cruise = Cruise::findOrFail($id);
            
            $cruise->name = $request->name;
            $cruise->thumbnail = $request->thumbnail;
            $cruise->destination = $request->destination;
            $cruise->description = $request->description;
            $cruise->duration_days = $request->durationDays;
            $cruise->duration_nights = $request->durationNights;
            $cruise->star_rating = $request->starRating;
            $cruise->status = $request->status;
            $cruise->save();

            // Trả về dữ liệu đã cập nhật để React render lại
            $updatedCruise = [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                'destination' => $cruise->destination,
                'durationDays' => (int) $cruise->duration_days,
                'durationNights' => (int) $cruise->duration_nights,
                'starRating' => (float) $cruise->star_rating,
                // Lấy giá basePrice hiện tại của tàu (Min của các phòng)
                'basePrice' => (float) ($cruise->cabinClasses()->min('price') ?? 0),
                'images' => [], 
                'description' => $cruise->description,
                'facilities' => [],
                'featured' => $cruise->status === 'active',
                'cabins' => $cruise->cabinClasses // Trả về kèm các phòng hiện có
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật thành công',
                'data' => $updatedCruise
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API 6: Xóa Du thuyền
     */
    public function deleteCruise($id)
    {
        try {
            $cruise = Cruise::findOrFail($id);
            $cruise->delete(); // Xóa khỏi Database

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xóa du thuyền thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Không thể xóa: ' . $e->getMessage()], 500);
        }
    }
    /**
     * API 7: Thêm Phòng mới (Cabin)
     */
    public function storeCabin(Request $request)
    {
        try {
            $cabin = new \App\Models\CabinClass();
            $cabin->cruise_id = $request->cruise_id; // Khóa ngoại nối với Tàu
            $cabin->name = $request->name;
            $cabin->area = $request->area ?? 20; // Diện tích phòng, mặc định 20m2 nếu chưa có dữ liệu
            $cabin->deck = $request->deck ?? 1; // Tầng tàu, mặc định tầng 1 nếu chưa có dữ liệu
            $cabin->price = $request->pricePerNight;
            $cabin->capacity = $request->capacity;
            $cabin->total_rooms = $request->available; 
            $cabin->available_rooms = $request->available;
            $cabin->image_url = $request->imageUrl;
            $cabin->save();

            // Format lại chuẩn UI
            $newCabin = [
                'id' => (string) $cabin->id,
                'type' => $request->type ?? 'Standard',
                'name' => $cabin->name,
                'area' => (float) $cabin->area,
                'deck' => (int) $cabin->deck,
                'pricePerNight' => (float) $cabin->price,
                'capacity' => (int) $cabin->capacity,
                'available' => (int) $cabin->available_rooms,
                'amenities' => [],
                'imageUrl' => $cabin->image_url ?? '',
            ];

            return response()->json(['status' => 'success', 'data' => $newCabin]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API 8: Cập nhật Phòng
     */
    public function updateCabin(Request $request, $id)
    {
        try {
            $cabin = \App\Models\CabinClass::findOrFail($id);
            $cabin->name = $request->name;
            $cabin->area = $request->area ?? $cabin->area; 
            $cabin->deck = $request->deck ?? $cabin->deck; 
            $cabin->price = $request->pricePerNight;
            $cabin->capacity = $request->capacity;
            $cabin->available_rooms = $request->available;
            $cabin->image_url = $request->imageUrl;
            $cabin->save();

            $updatedCabin = [
                'id' => (string) $cabin->id,
                'type' => $request->type ?? 'Standard',
                'name' => $cabin->name,
                'pricePerNight' => (float) $cabin->price,
                'capacity' => (int) $cabin->capacity,
                'available' => (int) $cabin->available_rooms,
                'amenities' => [],
                'imageUrl' => $cabin->image_url ?? '',
            ];

            return response()->json(['status' => 'success', 'data' => $updatedCabin]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API 9: Xóa Phòng
     */
    public function deleteCabin($id)
    {
        try {
            \App\Models\CabinClass::findOrFail($id)->delete();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * API 10: Quản lý Tài khoản 
     */
    public function getAccounts()
    {
        // Lấy danh sách, format ngày tháng cho đẹp
        $users = \App\Models\User::orderBy('created_at', 'desc')->get()->map(function ($u) {
            return [
                'id' => (string) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?? 'Chưa cập nhật',
                'role' => $u->role ?? 'Customer',
                'createdAt' => $u->created_at->format('d/m/Y')
            ];
        });
        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function storeAccount(Request $request)
    {
        try {
            $user = new \App\Models\User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->password = bcrypt($request->password); // Bắt buộc phải băm mật khẩu
            $user->role = $request->role;
            $user->save();

            $newData = [
                'id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email,
                'phone' => $user->phone, 'role' => $user->role, 'createdAt' => $user->created_at->format('d/m/Y')
            ];
            return response()->json(['status' => 'success', 'data' => $newData]);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    public function updateAccount(Request $request, $id)
    {
        try {
            $user = \App\Models\User::findOrFail($id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->role = $request->role;
            
            // Chỉ cập nhật mật khẩu nếu Admin có gõ pass mới
            if ($request->filled('password')) { 
                $user->password = bcrypt($request->password);
            }
            $user->save();

            $updatedData = [
                'id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email,
                'phone' => $user->phone, 'role' => $user->role, 'createdAt' => $user->created_at->format('d/m/Y')
            ];
            return response()->json(['status' => 'success', 'data' => $updatedData]);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }

    public function deleteAccount($id)
    {
        try {
            \App\Models\User::findOrFail($id)->delete();
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) { return response()->json(['status' => 'error'], 500); }
    }
      public function updateBookingStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:holding,confirmed,paid,completed,cancelled'
    ]);

    $booking = Booking::findOrFail($id);
    $booking->status = $request->status;
    $booking->save();

    return response()->json([
        'message' => 'Cập nhật trạng thái thành công',
        'data' => $booking
    ]);
}
}
