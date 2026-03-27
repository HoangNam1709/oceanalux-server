<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cruise;
use Illuminate\Http\Request;

class CruiseController extends Controller
{
    public function index()
    {
        // Lấy tất cả du thuyền, kèm theo dữ liệu từ bảng amenities (tiện ích)
        $cruises = Cruise::with('amenities')->get();

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
        $cruise = Cruise::with(['amenities', 'cabinClasses','images','reviews.user','itineraries'])->find($id);

        if (!$cruise) {
            return response()->json(['message' => 'Không tìm thấy du thuyền'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $cruise
        ]);
        
    }
}