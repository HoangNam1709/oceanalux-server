<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cruise;
use App\Models\Schedule;
use App\Models\CabinClass;
use Illuminate\Http\Request;

class CruiseController extends Controller
{
    /**
     * TÌM KIẾM DU THUYỀN
     */
    public function index(Request $request)
    {
        $query = Cruise::with(['amenities', 'images', 'cabinClasses']);

        // 1. TÌM THEO TÊN HOẶC ĐIỂM ĐẾN
        if ($request->filled('location')) {
            $location = $request->location;
            $query->where(function($q) use ($location) {
                $q->where('name', 'LIKE', '%' . $location . '%')
                  ->orWhere('destination', 'LIKE', '%' . $location . '%');
            });
        }

        // 2. TÌM THEO NGÀY KHỞI HÀNH VÀ PHÒNG TRỐNG
        // Logic mới: Phải kiểm tra tồn tại lịch trình VÀ còn phòng trong bảng PIVOT
        if ($request->filled('date')) {
            $date = date('Y-m-d', strtotime($request->date)); 
            $guests = (int) ($request->guests ?? 1);

            $query->whereHas('schedules', function($q) use ($date, $guests) {
                $q->whereDate('departure_date', $date)
                  ->whereNotIn('status', ['completed', 'cancelled']);

                // Kiểm tra xem trong ngày này có hạng phòng nào đủ chỗ và còn trống không
                $q->whereHas('cabin_classes', function($pivotQ) use ($guests) {
                    $pivotQ->where('capacity', '>=', $guests)
                           ->where('cabin_class_schedule.available_rooms', '>', 0);
                });
            });
        } 
        // 3. Nếu khách chỉ tìm theo số lượng người (không chọn ngày)
        elseif ($request->filled('guests')) {
            $guests = (int) $request->guests;
            $query->whereHas('cabinClasses', function($q) use ($guests) {
                $q->where('capacity', '>=', $guests);
                // Vì không có ngày cụ thể, ta chỉ lọc theo sức chứa thiết kế (total_rooms)
            });
        }

        $cruises = $query->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Lấy danh sách du thuyền thành công',
            'data' => $cruises
        ]);
    }

    /**
     * CHI TIẾT DU THUYỀN
     */
    public function show($id)
    {
        $cruise = Cruise::with([
            'amenities', 
            'cabinClasses.images', 
            'cabinClasses.amenities',
            'images',
            'reviews.user',
            'reviews.images',
            'itineraries',
            'schedules' => function($query) {
                $query->whereDate('departure_date', '>=', now()->toDateString()) 
                      ->whereNotIn('status', ['cancelled', 'completed'])         
                      ->orderBy('departure_date', 'asc');                        
            }
        ])->find($id);

        if (!$cruise) {
            return response()->json(['message' => 'Không tìm thấy du thuyền'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $cruise
        ]);
    }

    /**
     * LẤY PHÒNG TRỐNG THEO LỊCH TRÌNH (API CHỐT)
     */
    public function getAvailableCabins($id)
    {
        // 1. Lấy Lịch trình cụ thể, bốc dữ liệu từ bảng trung gian (Pivot)
        $schedule = Schedule::with([
            'cabin_classes.amenities', 
            'cabin_classes.images'
        ])->find($id);

        // 2. Nếu chưa có lịch trình (Dữ liệu hiển thị mặc định cho Frontend khi mới vào trang)
        if (!$schedule) {
            $cruiseId = request('cruise_id');
            $defaultCabins = CabinClass::with(['amenities', 'images'])
                ->where('cruise_id', $cruiseId)
                ->get()
                ->map(function($cabin) {
                    // Dùng total_rooms làm giá trị mặc định để Frontend ko bị trống
                    $cabin->available_rooms = $cabin->total_rooms; 
                    return $cabin;
                });

            return response()->json([
                'status' => 'success',
                'data' => $defaultCabins,
                'note' => 'Hiển thị theo Total Rooms (Dữ liệu mặc định)'
            ]);
        }

        // 3. Map lại dữ liệu CHUẨN từ bảng trung gian
        $cabins = $schedule->cabin_classes->map(function ($cabin) {
            return [
                'id' => $cabin->id,
                'name' => $cabin->name,
                'price' => $cabin->price,
                'capacity' => $cabin->capacity,
                'description' => $cabin->description,
                'total_rooms' => $cabin->total_rooms,
                
                // 🎯 ĐÂY LÀ GIÁ TRỊ VÀNG: Bốc từ Pivot
                'available_rooms' => $cabin->pivot->available_rooms, 
                
                'amenities' => $cabin->amenities,
                'images' => $cabin->images,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $cabins
        ]);
    }
}