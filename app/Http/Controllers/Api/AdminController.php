<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\CabinClass;
use App\Models\Schedule;
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
        //  Thêm withTrashed() để lấy được tên Tàu và Phòng dù đã bị xóa mềm
        $bookings = Booking::with([
            'schedule.cruise' => function($query) {
                $query->withTrashed(); 
            }, 
            'details.cabinClass' => function($query) {
                $query->withTrashed();
            }
        ])
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
                'cancellation_reason' => $booking->cancellation_reason,
                'refund_amount' => $booking->refund_amount,
                'refund_status' => $booking->refund_status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedBookings
        ]);
    }
    /**
     * API 3: Lấy danh sách Du thuyền kèm theo Phòng, Ảnh và Tiện ích, lịch trình
     */
    public function getCruises()
    {
        // Gọi dữ liệu Tàu kèm theo các bảng liên quan để tránh lỗi N+1 Query
        $cruises = Cruise::with(['cabinClasses', 'images', 'amenities', 'schedules'])
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
                'schedules' => $cruise->schedules,
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
     * API 6: Xóa Du thuyền (LOGIC MỚI: Chỉ chặn đơn chưa hoàn thành)
     */
    public function deleteCruise($id)
    {
        try {
            $cruise = Cruise::findOrFail($id);

            // TÌM CÁC ĐƠN HÀNG ĐANG "SỐNG" CỦA TÀU NÀY
            $hasActiveBookings = \App\Models\Booking::whereHas('schedule', function($q) use ($id) {
                $q->where('cruise_id', $id);
            })->whereNotIn('status', ['completed', 'cancelled'])->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa tàu '{$cruise->name}' vì đang có hành khách đặt chỗ chưa hoàn thành chuyến đi. Vui lòng xử lý hết đơn hàng trước khi xóa.",
                    'code' => 'CRUISE_HAS_ACTIVE_BOOKINGS'
                ], 400);
            }

            // An toàn để xóa
            $cruise->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Đã xóa du thuyền '{$cruise->name}' thành công!"
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
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
            $cabin = \App\Models\CabinClass::findOrFail($id);

            // Tìm các Đơn hàng đang "Sống" có chứa Hạng phòng này
            $hasActiveBookings = \App\Models\BookingDetail::where('cabin_class_id', $id)
                ->whereHas('booking', function($q) {
                    $q->whereNotIn('status', ['completed', 'cancelled']);
                })->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa Hạng phòng '{$cabin->name}' vì đang có khách đặt chờ trải nghiệm. Vui lòng đợi khách đi xong hoặc hủy đơn.",
                    'code' => 'CABIN_HAS_ACTIVE_BOOKINGS'
                ], 400);
            }

            // An toàn để xóa
            $cabin->delete();
            
            return response()->json(['status' => 'success', 'message' => 'Đã xóa Hạng phòng thành công.']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
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

    /**
     * API 10.x: Xóa Tài khoản (CÓ RÀNG BUỘC)
     */
    public function deleteAccount($id)
    {
        try {
            $user = \App\Models\User::findOrFail($id);

            // Kiểm tra xem User này đã từng đặt vé chưa
            $hasBookings = \App\Models\Booking::where('user_id', $id)->exists();

            if ($hasBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa tài khoản '{$user->email}' vì khách hàng này đã có lịch sử giao dịch. Xóa tài khoản sẽ làm hỏng dữ liệu báo cáo.",
                    'code' => 'USER_HAS_BOOKINGS'
                ], 400);
            }

            $user->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa tài khoản thành công.']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy Tài khoản này.'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
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
    /**
     * API 11: Lấy dữ liệu Tồn phòng & Tỷ lệ lấp đầy
     */
    public function getSchedulesHealth()
    {
        try {
            // 1. Lấy các chuyến đi đang mở bán (Từ hôm nay trở đi và chưa hoàn thành/hủy)
            $schedules = Schedule::with(['cruise', 'cabin_classes'])
                ->whereDate('departure_date', '>=', now()->toDateString())
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->orderBy('departure_date', 'asc') // Chuyến nào bay sớm xếp lên đầu
                ->get();

            // 2. Map dữ liệu để tính toán các chỉ số cho Admin
            $healthData = $schedules->map(function ($schedule) {
                $totalRooms = 0;
                $availableRooms = 0;

                // 🎯 Lặp qua các hạng phòng của lịch trình này để cộng dồn từ Pivot
                foreach ($schedule->cabin_classes as $cabin) {
                    $totalRooms += $cabin->total_rooms;
                    $availableRooms += $cabin->pivot->available_rooms; // Bốc từ bảng trung gian
                }

                // Tính số phòng đã có khách cọc/thanh toán
                $bookedRooms = $totalRooms - $availableRooms;

                // Tính tỷ lệ lấp đầy (%) - Xử lý an toàn để tránh lỗi chia cho 0
                $occupancyRate = $totalRooms > 0 
                    ? round(($bookedRooms / $totalRooms) * 100, 2) 
                    : 0;

               // Đóng gói dữ liệu trả về cho React
            return [
                'schedule_id' => $schedule->id,
                'cruise_name' => $schedule->cruise->name ?? 'Tàu chưa rõ tên',
                'departure_date' => \Carbon\Carbon::parse($schedule->departure_date)->format('d/m/Y'),
                'return_date' => \Carbon\Carbon::parse($schedule->return_date)->format('d/m/Y'),
                'status' => $schedule->status,
                
                // Các chỉ số vàng cho Admin:
                'metrics' => [
                    'total_rooms' => $totalRooms,
                    'available_rooms' => $availableRooms,
                    'booked_rooms' => $bookedRooms,
                    'occupancy_rate' => $occupancyRate,
                ],

        
                'cabin_details' => $schedule->cabin_classes->map(function($cabin) {
                    return [
                        'id' => $cabin->id,
                        'name' => $cabin->name,
                        'type' => 'Ocean View', // Chỉnh lại theo thiết kế DB của bạn
                        'pricePerNight' => (float) $cabin->price,
                        'total_rooms' => $cabin->total_rooms,
                        'available_rooms' => $cabin->pivot->available_rooms,
                    ];
                })->values()
            ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $healthData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tính toán tình trạng phòng: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * API 12: Xuất Excel Báo cáo Doanh Thu
     */
    public function exportRevenueExcel(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $fileName = 'OceanaLux_BaoCaoDoanhThu_' . now()->format('Y_m_d_H_i') . '.xlsx';

        // Gọi Class Export mà chúng ta vừa tạo
        return (new \App\Exports\RevenueExport($startDate, $endDate))->download($fileName);
    }
    public function getRevenueStats(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-01')); 
            $endDate = $request->query('end_date', date('Y-m-t'));
            $cruiseId = $request->query('cruise_id', 'all');

            // --- THÊM RÀNG BUỘC LOGIC Ở ĐÂY ---
            if (strtotime($endDate) < strtotime($startDate)) {
                throw new \Exception("Lỗi: Ngày kết thúc không được nhỏ hơn ngày bắt đầu.");
            }

            // 1. TÍNH TOÁN KỲ HIỆN TẠI
            $currentQuery = Booking::whereIn('status', ['paid', 'completed', 'confirmed']);
            if ($cruiseId !== 'all') {
                $currentQuery->whereHas('schedule', function($q) use ($cruiseId) { $q->where('cruise_id', $cruiseId); });
            }

            $currentTotal = (clone $currentQuery)->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');
            $currentRecognized = (clone $currentQuery)->where('status', 'completed')->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');
            $currentRefund = Booking::where('status', 'cancelled')->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');

            // 2. TÍNH TOÁN KỲ TRƯỚC (Để tính % tăng trưởng)
            $duration = strtotime($endDate) - strtotime($startDate);
            $prevStartDate = date('Y-m-d', strtotime($startDate) - $duration - 86400);
            $prevEndDate = date('Y-m-d', strtotime($startDate) - 86400);
            
            $prevTotal = (clone $currentQuery)->whereBetween('created_at', [$prevStartDate.' 00:00:00', $prevEndDate.' 23:59:59'])->sum('total_price');
            $growth = $prevTotal > 0 ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1) : 100;

            // 3. CƠ CẤU DOANH THU THEO TÀU (Cho biểu đồ tròn)
            //  Dùng withTrashed() để báo cáo không bỏ sót doanh thu của tàu đã xóa
            $cruisesData = Cruise::withTrashed()->get()->map(function($cruise) use ($startDate, $endDate) {
                $revenue = Booking::whereIn('status', ['paid', 'completed', 'confirmed'])
                    ->whereHas('schedule', function($q) use ($cruise) { 
                        $q->where('cruise_id', $cruise->id); 
                    })
                    ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->sum('total_price');
                return ['name' => $cruise->name, 'value' => (float)$revenue];
            })->filter(fn($item) => $item['value'] > 0)->values();

            // 4. BIỂU ĐỒ ĐƯỜNG 12 THÁNG
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = [
                    'name' => 'T' . $i,
                    'total' => (float) (clone $currentQuery)->whereYear('created_at', date('Y', strtotime($startDate)))->whereMonth('created_at', $i)->sum('total_price')
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'metrics' => [
                        'totalCashIn' => (float)$currentTotal,
                        'recognizedRevenue' => (float)$currentRecognized,
                        'refundedAmount' => (float)$currentRefund,
                        'growth' => $growth
                    ],
                    'chartData' => $chartData,
                    'cruisesData' => $cruisesData,
                    'year' => date('Y', strtotime($startDate))
                ]
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500); }
    }
    /**
     * Lấy dữ liệu thống kê cho trang Overview
     */
    public function getOverviewStats()
    {
        try {
            $currentYear = date('Y');
            $currentMonth = date('m');

            // 1. Tính toán các chỉ số tổng quan
            $totalBookings = \App\Models\Booking::count();
            $confirmedBookings = \App\Models\Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->count();
            $totalGuests = \App\Models\Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->sum('guests');
            $totalRevenue = \App\Models\Booking::whereIn('status', ['paid', 'completed'])->sum('total_price');

            // 2. Tính dữ liệu biểu đồ doanh thu của năm hiện tại
            $monthlyRevenue = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthTotal = \App\Models\Booking::whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $i)
                    ->whereIn('status', ['paid', 'completed'])
                    ->sum('total_price');

                $monthlyRevenue[] = [
                    'month' => 'Tháng ' . $i,
                    'value' => (float) $monthTotal
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'stats' => [
                        'totalBookings' => $totalBookings,
                        'confirmedBookings' => $confirmedBookings,
                        'totalGuests' => (int) $totalGuests,
                        'totalRevenue' => (float) $totalRevenue,
                    ],
                    'monthlyRevenue' => $monthlyRevenue
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    public function getSchedules(Request $request)
    {
        try {
            $cruiseId = $request->query('cruise_id');

            $query = Schedule::query();

            // Nếu truyền lên cruise_id thì chỉ lấy lịch trình của tàu đó
            if ($cruiseId) {
                $query->where('cruise_id', $cruiseId);
            }

            // Lấy lịch trình, sắp xếp ngày khởi hành mới nhất lên đầu
            $schedules = $query->orderBy('departure_date', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $schedules
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi tải danh sách lịch trình: ' . $e->getMessage()
            ], 500);
        }
    }
   /**
     * API 13: Thêm Lịch trình mới (
     */
    public function storeSchedule(Request $request)
    {
        try {
            $cruise = \App\Models\Cruise::findOrFail($request->cruise_id);
            $departureDate = \Carbon\Carbon::parse($request->departure_date)->startOfDay();
            $today = \Carbon\Carbon::today();

            //Không được mở bán ở quá khứ
            if ($departureDate->lt($today)) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Rất tiếc! Ngày khởi hành không được nhỏ hơn ngày hôm nay.'
                ], 400);
            }

            // Chống trùng lịch trình
            // Kiểm tra xem con tàu này đã có lịch khởi hành vào đúng ngày này chưa
            $isDuplicate = Schedule::where('cruise_id', $request->cruise_id)
                ->whereDate('departure_date', $departureDate->format('Y-m-d'))
                ->exists();

            if ($isDuplicate) {
                return response()->json([
                    'status' => 'error', 
                    'message' => "Tàu này đã có lịch khởi hành vào ngày " . $departureDate->format('d/m/Y') . ". Vui lòng chọn ngày khác!"
                ], 400); // Trả về lỗi 400 để React hiển thị màu đỏ
            }

            //Tự động tính Ngày Về
            $daysToAdd = max(0, $cruise->duration_days - 1);
            $returnDate = $departureDate->copy()->addDays($daysToAdd);

            // Xử lý lưu vào Database
            $schedule = new Schedule();
            $schedule->cruise_id = $request->cruise_id;
            $schedule->departure_date = $departureDate; 
            $schedule->return_date = $returnDate; 
            $schedule->status = $request->status ?? 'upcoming'; // Lưu mặc định là upcoming
            $schedule->save();

            // Khởi tạo kho phòng
            $cabins = \App\Models\CabinClass::where('cruise_id', $request->cruise_id)->get();
            foreach ($cabins as $cabin) {
                $schedule->cabin_classes()->attach($cabin->id, [
                    'available_rooms' => $cabin->total_rooms ?? $cabin->available_rooms ?? 0
                ]);
            }

            return response()->json([
                'status' => 'success', 
                'message' => 'Đã mở bán Lịch trình mới thành công!',
                'data' => $schedule->load('cabin_classes') 
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi khởi tạo lịch trình: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API 14: Cập nhật Lịch trình 
     */
    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            // KIỂM TRA: Nếu đã có khách đặt, TUYỆT ĐỐI không cho đổi ngày đi/ngày về
            $hasBookings = \App\Models\Booking::where('schedule_id', $id)->whereNotIn('status', ['cancelled'])->exists();
            
            $isChangingDates = ($schedule->departure_date != $request->departure_date) || ($schedule->return_date != $request->return_date);

            if ($hasBookings && $isChangingDates) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lịch trình này đã có hành khách đặt vé. Bạn không thể thay đổi Ngày đi/Ngày về để tránh ảnh hưởng đến khách hàng. Bạn chỉ có thể cập nhật trạng thái.',
                ], 400);
            }

            // Nếu an toàn, tiến hành cập nhật
            $schedule->departure_date = $request->departure_date;
            $schedule->return_date = $request->return_date;
            $schedule->status = $request->status ?? $schedule->status;
            $schedule->save();

            return response()->json([
                'status' => 'success', 
                'message' => 'Cập nhật lịch trình thành công!',
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

   /**
     * API 15: Xóa Lịch trình
     */
    public function deleteSchedule($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);

            $hasActiveBookings = \App\Models\Booking::where('schedule_id', $id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa Lịch trình ngày " . \Carbon\Carbon::parse($schedule->departure_date)->format('d/m/Y') . " do có hành khách đã đặt vé và chưa hoàn thành chuyến đi.",
                    'code' => 'SCHEDULE_HAS_ACTIVE_BOOKINGS'
                ], 400); 
            }

            $schedule->delete();

            return response()->json([
                'status' => 'success', 
                'message' => 'Đã xóa lịch trình thành công!'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Lịch trình này không tồn tại hoặc đã bị xóa từ trước!'
            ], 404); // Trả về mã lỗi 404 Not Found
            
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }
    /**
     * API Admin: Xác nhận đã hoàn tiền cho khách
     */
    public function processRefund(Request $request, $id)
    {
        try {
            $booking = \App\Models\Booking::findOrFail($id);

            // Kiểm tra xem đơn có đang chờ hoàn tiền không
            if ($booking->status !== 'cancelled' || $booking->refund_status !== 'pending') {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Đơn hàng này không trong trạng thái chờ hoàn tiền!'
                ], 400);
            }

            // Kế toán có thể lưu lại Mã giao dịch ngân hàng vào cột note/reason nếu cần
            if ($request->has('admin_note')) {
                $booking->cancellation_reason = $booking->cancellation_reason . "\n[Kế toán]: " . $request->admin_note;
            }

            // Chuyển trạng thái sang đã hoàn
            $booking->refund_status = 'refund';
            $booking->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã xác nhận hoàn tiền cho đơn hàng ' . $booking->booking_code
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }
    /**
     * API ADMIN: Xử lý Hủy đơn & Chuyển sang chờ hoàn tiền
     */
    public function cancelAndRefundBooking(Request $request, $id)
    {
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // 1. Tìm đơn hàng (Admin không cần check user_id)
            $booking = \App\Models\Booking::find($id);

            if (!$booking) {
                return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
            }

            // Chỉ xử lý đơn đã thanh toán
            if ($booking->status !== 'paid') {
                return response()->json(['status' => 'error', 'message' => 'Chỉ hỗ trợ hủy và hoàn tiền cho đơn đã thanh toán!'], 400);
            }

            // 2. Logic tính toán tiền hoàn (Chuẩn chính sách)
            $schedule = \App\Models\Schedule::findOrFail($booking->schedule_id);
            $daysUntilDeparture = \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($schedule->departure_date)->startOfDay(), false);

            $totalPrice = $booking->total_price;
            if ($daysUntilDeparture >= 7) {
                $refundAmount = $totalPrice; // Hoàn 100%
                $cancellationFee = 0;
            } elseif ($daysUntilDeparture >= 3 && $daysUntilDeparture <= 6) {
                $refundAmount = $totalPrice * 0.5; // Hoàn 50%
                $cancellationFee = $totalPrice * 0.5;
            } else {
                $refundAmount = 0; // Mất trắng
                $cancellationFee = $totalPrice; 
            }

            // 3. Admin nhả phòng để bán lại
            $success = $booking->releaseRoom();
            if (!$success) {
                throw new \Exception("Lỗi hệ thống khi giải phóng kho phòng.");
            }

            // 4. Cập nhật trạng thái
            $booking->status = 'cancelled';
            // Gắn mác [Admin] để phân biệt với khách tự hủy
            $booking->cancellation_reason = "[Admin Xác Nhận Hủy]: " . ($request->reason ?? 'Không có lý do');
            $booking->cancellation_fee = $cancellationFee;
            $booking->refund_amount = $refundAmount;
            $booking->cancelled_at = now();
            
            // Nếu có tiền hoàn thì báo cho Kế toán
            if ($refundAmount > 0) {
                $booking->refund_status = 'pending'; 
            }
            
            $booking->save();

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'status' => 'success', 
                'message' => 'Đã hủy đơn hàng và chuyển sang danh sách chờ hoàn tiền!'
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }
}
