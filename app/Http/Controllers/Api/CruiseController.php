<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cruise;
use Illuminate\Http\Request;

class CruiseController extends Controller
{
    public function index(Request $request)
{
    // 1. Khởi tạo query và load sẵn các quan hệ cần thiết
    // Thêm 'images' và 'cabinClasses' để trang kết quả tìm kiếm có ảnh và tính được giá min
    $query = Cruise::with(['amenities', 'images', 'cabinClasses']);

    // 2. TÌM THEO TÊN HOẶC ĐIỂM ĐẾN
    if ($request->filled('location')) {
        $location = $request->location;
        $query->where(function($q) use ($location) {
            $q->where('name', 'LIKE', '%' . $location . '%')
              ->orWhere('destination', 'LIKE', '%' . $location . '%');
        });
    }

    // 3. TÌM THEO NGÀY KHỞI HÀNH
    if ($request->filled('date')) {
        // Chuyển đổi định dạng ngày cho an toàn
        $date = date('Y-m-d', strtotime($request->date)); 
        
        $query->whereHas('schedules', function($q) use ($date) {
            // Tìm các tàu có lịch trình trùng khớp ngày và trạng thái chưa hoàn thành/hủy
            $q->whereDate('departure_date', $date)
              ->whereNotIn('status', ['completed', 'cancelled']); 
        });
    }

    // 4. TÌM THEO SỐ LƯỢNG KHÁCH
    if ($request->filled('guests')) {
        $guests = (int) $request->guests;
        $query->whereHas('cabinClasses', function($q) use ($guests) {
            // Tìm tàu có hạng phòng chứa đủ khách VÀ còn phòng trống
            $q->where('capacity', '>=', $guests)
              ->where('available_rooms', '>', 0);
        });
    }

    // 5. Thực thi query lấy dữ liệu (Dùng get() như code cũ của bạn)
    $cruises = $query->get();

    // Trả về định dạng JSON chuẩn bị sẵn cho ReactJS
    return response()->json([
        'status' => 'success',
        'message' => 'Lấy danh sách du thuyền thành công',
        'data' => $cruises
    ]);
}
    public function show($id)
    {
        // Lấy chi tiết 1 tàu, kèm theo dữ liệu bảng Tiện ích (amenities) và Hạng phòng (cabinClasses)
        // Lưu ý: Nhớ thêm hàm cabinClasses() vào Model Cruise giống như hàm amenities() nhé!
        $cruise = Cruise::with(['amenities', 'cabinClasses','images','reviews.user','itineraries','cabinClasses.images', 
            'cabinClasses.amenities','schedules'])->find($id);

        if (!$cruise) {
            return response()->json(['message' => 'Không tìm thấy du thuyền'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $cruise
        ]);
        
    }
    public function getAvailableCabins($id)
    {
        // 1. Lấy thông tin Lịch trình
        $schedule = \App\Models\Schedule::findOrFail($id);
        
        // 2. Lấy tất cả Hạng phòng của con tàu này kèm tiện ích và ảnh
        $cabins = \App\Models\CabinClass::with(['amenities', 'images'])
                    ->where('cruise_id', $schedule->cruise_id)
                    ->get();

        // 3. Tính toán số phòng CÒN TRỐNG cho từng hạng phòng TRONG NGÀY NÀY
        $cabins = $cabins->map(function ($cabin) use ($id) {
            
            // Đếm số lượng phòng đã bị đặt (trừ các đơn đã hủy)
            $bookedRooms = \App\Models\BookingDetail::where('cabin_class_id', $cabin->id)
                ->whereHas('booking', function($query) use ($id) {
                    $query->where('schedule_id', $id)
                          ->whereIn('status', ['holding', 'pending', 'confirmed', 'paid']); // Đang giữ hoặc đã thanh toán
                })
                ->sum('quantity'); // Giả sử bảng chi tiết có cột quantity (số lượng phòng)
            
            // Nếu DB của bạn mỗi dòng là 1 phòng, thì dùng ->count() thay vì sum()

            // Gán lại số phòng available (Tối thiểu là 0 để không bị số âm)
            $cabin->available_rooms = max(0, $cabin->total_rooms - $bookedRooms);
            
            return $cabin;
        });

        return response()->json([
            'status' => 'success',
            'data' => $cabins
        ]);
    }
}