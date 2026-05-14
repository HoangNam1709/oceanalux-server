<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\CabinClass;
use App\Models\Schedule;
use App\Models\Amenity;
use App\Models\User;
use App\Models\CabinImage;
use App\Models\CruiseImage;
use App\Models\BookingDetail;
use App\Models\Holiday;
use Carbon\Carbon;

class AdminController extends Controller
{
    // =====================================================================
    // 1. TỔNG QUAN & THỐNG KÊ (DASHBOARD & OVERVIEW)
    // =====================================================================

    public function getDashboardStats()
    {
        try {
            $totalBookings = Booking::count();
            $confirmedBookings = Booking::whereIn('status', ['paid', 'completed'])->count();
            $totalRevenue = Booking::whereIn('status', ['paid', 'completed'])->sum('total_price');
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
            return response()->json(['status' => 'error', 'message' => 'Lỗi sập Server: ' . $e->getMessage()], 500);
        }
    }

    public function getOverviewStats()
    {
        try {
            $currentYear = date('Y');
            $totalBookings = Booking::count();
            $confirmedBookings = Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->count();
            $totalGuests = Booking::whereIn('status', ['confirmed', 'paid', 'completed'])->sum('guests');
            $totalRevenue = Booking::whereIn('status', ['paid', 'completed'])->sum('total_price');

            $monthlyRevenue = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthTotal = Booking::whereYear('created_at', $currentYear)
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

    // =====================================================================
    // 2. QUẢN LÝ DU THUYỀN (CRUISES)
    // =====================================================================

    public function getCruises()
    {
        // Load kèm ảnh của Cruise và ảnh của CabinClasses để đóng gói objects
        $cruises = Cruise::with(['cabinClasses.images', 'images', 'amenities', 'schedules','itineraries'])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedCruises = $cruises->map(function ($cruise) {
            $basePrice = $cruise->cabinClasses->min('price') ?? 0;

            // Map ảnh Tàu
            $cruiseImages = $cruise->images->map(function($img) {
                return ['id' => $img->id, 'image_url' => $img->image_url];
            })->toArray();

            return [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                'destination' => $cruise->destination ?? 'Vịnh Hạ Long',
                'durationDays' => (int) ($cruise->duration_days ?? 3),
                'durationNights' => (int) ($cruise->duration_nights ?? 2),
                'starRating' => (float) ($cruise->star_rating ?? 5),
                'basePrice' => (float) $basePrice,
                'images_objects' => $cruiseImages,
                'images' => $cruise->images->pluck('image_url')->toArray(),
                'description' => $cruise->description ?? '',
                'facilities'  => $cruise->amenities->pluck('name')->toArray(),
                'facilityIds' => $cruise->amenities->pluck('id')->toArray(),
                'featured' => $cruise->status === 'active',
                'schedules' => $cruise->schedules,
                'itineraries' => $cruise->itineraries,  
                // Map dữ liệu Hạng phòng (Cabin)
                'cabins' => $cruise->cabinClasses->map(function ($cabin) {
                    $cabinImages = $cabin->images->map(function($img) {
                        return ['id' => $img->id, 'image_url' => $img->image_url];
                    })->toArray();

                    return [
                        'id' => (string) $cabin->id,
                        'type' => $cabin->type ?? 'Standard',
                        'name' => $cabin->name,
                        'pricePerNight' => (float) $cabin->price,
                        'capacity' => (int) $cabin->capacity,
                        'available' => (int) $cabin->available_rooms,
                        'amenities' => [],
                        'imageUrl' => $cabin->image_url ?? '',
                        'area' => (float) $cabin->area,
                        'deck' => (int) $cabin->deck,
                        'images_objects' => $cabinImages // Trả về cho UI Thư viện ảnh Phòng
                    ];
                })->values()->toArray()
            ];
        });

        return response()->json(['status' => 'success', 'data' => $formattedCruises]);
    }

    public function storeCruise(Request $request)
    {
        try {
            $cruise = new Cruise();
            $cruise->name = $request->name;
            $cruise->thumbnail = $request->thumbnail;
            $cruise->destination = $request->destination ?? 'Đang cập nhật';
            $cruise->duration_days = $request->durationDays ?? 3;
            $cruise->duration_nights = $request->durationNights ?? 2;
            $cruise->description = $request->description;
            $cruise->star_rating = $request->starRating ?? 5;
            $cruise->status = $request->status ?? 'active';
            $cruise->save();

            if ($request->has('facilityIds')) {
                $cruise->amenities()->sync($request->facilityIds);
            }

            $cruise->load('amenities');

            $newCruise = [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                'destination' => $cruise->destination,
                'durationDays' => (int) $cruise->duration_days,
                'durationNights' => (int) $cruise->duration_nights,
                'starRating' => (float) $cruise->star_rating,
                'basePrice' => 0,
                'images' => [],
                'images_objects' => [], // Trả mảng rỗng vì chưa có ảnh
                'description' => $cruise->description,
                'featured' => $cruise->status === 'active',
                'cabins' => [],
                'facilities' => $cruise->amenities->pluck('name')->toArray(),
                'facilityIds' => $cruise->amenities->pluck('id')->toArray()
            ];

            return response()->json(['status' => 'success', 'data' => $newCruise]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

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

            if ($request->has('facilityIds')) {
                $cruise->amenities()->sync($request->facilityIds);
            }

            // Load lại đầy đủ relation để trả về
            $cruise->load(['amenities', 'images', 'cabinClasses']);

            $images_objects = $cruise->images->map(function($img) {
                return ['id' => $img->id, 'image_url' => $img->image_url];
            })->toArray();

            $updatedCruise = [
                'id' => (string) $cruise->id,
                'name' => $cruise->name,
                'thumbnail' => $cruise->thumbnail,
                'destination' => $cruise->destination,
                'durationDays' => (int) $cruise->duration_days,
                'durationNights' => (int) $cruise->duration_nights,
                'starRating' => (float) $cruise->star_rating,
                'basePrice' => (float) ($cruise->cabinClasses->min('price') ?? 0),
                'images' => $cruise->images->pluck('image_url')->toArray(),
                'images_objects' => $images_objects,
                'description' => $cruise->description,
                'featured' => $cruise->status === 'active',
                'cabins' => $cruise->cabinClasses,
                'facilities' => $cruise->amenities->pluck('name')->toArray(),
                'facilityIds' => $cruise->amenities->pluck('id')->toArray()
            ];

            return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công', 'data' => $updatedCruise]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    public function deleteCruise($id)
    {
        try {
            $cruise = Cruise::findOrFail($id);

            $hasActiveBookings = Booking::whereHas('schedule', function($q) use ($id) {
                $q->where('cruise_id', $id);
            })->whereNotIn('status', ['completed', 'cancelled'])->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa tàu '{$cruise->name}' vì đang có hành khách đặt chỗ chưa hoàn thành chuyến đi.",
                    'code' => 'CRUISE_HAS_ACTIVE_BOOKINGS'
                ], 400);
            }

            $cruise->delete();
            return response()->json(['status' => 'success', 'message' => "Đã xóa du thuyền '{$cruise->name}' thành công!"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================================
    // 3. QUẢN LÝ PHÒNG (CABINS)
    // =====================================================================

    public function storeCabin(Request $request)
    {
        $request->validate([
            'cruise_id'     => 'required|integer',
            'name'          => 'required|string|max:255',
            'pricePerNight' => 'required|numeric|min:0',
            'capacity'      => 'required|integer|min:1',
            'available'     => 'required|integer|min:0',
            'area'          => 'nullable|numeric|min:0',
            'deck'          => 'nullable|integer|min:1',
            'imageUrl'      => 'nullable|string',
            'type'          => 'nullable|string',
        ]);

        try {
            $cabin = new CabinClass();
            $cabin->cruise_id       = $request->cruise_id;
            $cabin->name            = $request->name;
            $cabin->area            = $request->area ?? 20;
            $cabin->deck            = $request->deck ?? 1;
            $cabin->price           = $request->pricePerNight;
            $cabin->capacity        = $request->capacity;
            $cabin->total_rooms     = $request->available;
            $cabin->available_rooms = $request->available;
            $cabin->image_url       = $request->imageUrl;
            $cabin->save();

            $newCabin = [
                'id'            => (string) $cabin->id,
                'type'          => $request->type ?? 'Standard',
                'name'          => $cabin->name,
                'area'          => (float) $cabin->area,
                'deck'          => (int) $cabin->deck,
                'pricePerNight' => (float) $cabin->price,
                'capacity'      => (int) $cabin->capacity,
                'available'     => (int) $cabin->available_rooms,
                'amenities'     => $request->amenities ?? [],
                'imageUrl'      => $cabin->image_url ?? '',
                'images_objects'=> [], // Phòng mới chưa có ảnh gallery
            ];

            return response()->json(['status' => 'success', 'data' => $newCabin]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCabin(Request $request, $id)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'pricePerNight' => 'required|numeric|min:0',
            'capacity'      => 'required|integer|min:1',
            'available'     => 'required|integer|min:0',
            'area'          => 'nullable|numeric|min:0',
            'deck'          => 'nullable|integer|min:1',
            'imageUrl'      => 'nullable|string',
            'type'          => 'nullable|string',
        ]);

        try {
            $cabin = CabinClass::findOrFail($id);
            $cabin->name            = $request->name;
            $cabin->area            = $request->area ?? $cabin->area;
            $cabin->deck            = $request->deck ?? $cabin->deck;
            $cabin->price           = $request->pricePerNight;
            $cabin->capacity        = $request->capacity;
            $cabin->available_rooms = $request->available;
            $cabin->image_url       = $request->imageUrl;
            $cabin->save();

            $cabin->load('images');
            $cabinImages = $cabin->images->map(function($img) {
                return ['id' => $img->id, 'image_url' => $img->image_url];
            })->toArray();

            $updatedCabin = [
                'id'            => (string) $cabin->id,
                'type'          => $request->type ?? 'Standard',
                'name'          => $cabin->name,
                'area'          => (float) $cabin->area,
                'deck'          => (int) $cabin->deck,
                'pricePerNight' => (float) $cabin->price,
                'capacity'      => (int) $cabin->capacity,
                'available'     => (int) $cabin->available_rooms,
                'amenities'     => $request->amenities ?? [],
                'imageUrl'      => $cabin->image_url ?? '',
                'images_objects'=> $cabinImages, // Gói ảnh cũ trả lại cho React
            ];

            return response()->json(['status' => 'success', 'data' => $updatedCabin]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteCabin($id)
    {
        try {
            $cabin = CabinClass::findOrFail($id);

            $hasActiveBookings = BookingDetail::where('cabin_class_id', $id)
                ->whereHas('booking', function($q) {
                    $q->whereNotIn('status', ['completed', 'cancelled']);
                })->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Không thể xóa Hạng phòng '{$cabin->name}' vì đang có khách đặt chờ trải nghiệm.",
                    'code' => 'CABIN_HAS_ACTIVE_BOOKINGS'
                ], 400);
            }

            $cabin->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa Hạng phòng thành công.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================================
    // 4. QUẢN LÝ ẢNH (IMAGE GALLERY)
    // =====================================================================

    public function addGalleryImage(Request $request, $type, $id)
    {
        $request->validate(['url' => 'required|string']);

        if ($type === 'cruise') {
            $image = CruiseImage::create(['cruise_id' => $id, 'image_url' => $request->url]);
        } else {
            $image = CabinImage::create(['cabin_class_id' => $id, 'image_url' => $request->url]);
        }

        return response()->json(['status' => 'success', 'data' => $image]);
    }

    public function deleteGalleryImage($type, $imageId)
    {
        if ($type === 'cruise') {
            CruiseImage::destroy($imageId);
        } else {
            CabinImage::destroy($imageId);
        }
        return response()->json(['status' => 'success', 'message' => 'Đã xóa ảnh']);
    }

    public function setAsThumbnail(Request $request, $type, $id)
    {
        $url = $request->url;
        if ($type === 'cruise') {
            $item = Cruise::findOrFail($id);
            $item->thumbnail = $url;
        } else {
            $item = CabinClass::findOrFail($id);
            $item->image_url = $url;
        }
        $item->save();

        return response()->json(['status' => 'success', 'message' => 'Đã cập nhật ảnh bìa']);
    }

    // =====================================================================
    // 5. QUẢN LÝ TIỆN ÍCH (AMENITIES)
    // =====================================================================

    public function getAllAmenities()
    {
        $amenities = Amenity::select('id', 'name')->get();
        return response()->json(['status' => 'success', 'data' => $amenities]);
    }

  public function getBookings()
{
    $bookings = Booking::with([
        'schedule.cruise' => function($query) { $query->withTrashed(); },
        'details.cabinClass' => function($query) { $query->withTrashed(); }
    ])->orderBy('created_at', 'desc')->get();

    $formattedBookings = $bookings->map(function ($booking) {
        $departureDate = $booking->schedule ? Carbon::parse($booking->schedule->departure_time)->format('d/m/Y') : 'N/A';
        // Giả sử tour 3 ngày 2 đêm nên cộng thêm 2 ngày cho ngày về
        $returnDate = $booking->schedule ? Carbon::parse($booking->schedule->departure_time)->addDays(2)->format('d/m/Y') : 'N/A';
        $bookedDate = $booking->created_at ? $booking->created_at->format('d/m/Y') : 'N/A';

        // Xác định số tiền hiển thị chính: Nếu đã hủy thì hiện tiền hoàn, còn lại hiện tổng bill
        $displayAmount = ($booking->status === 'cancelled') 
            ? (float) $booking->refund_amount 
            : (float) $booking->total_price;

        return [
            'id'                  => (string) $booking->id,
            'bookingRef'          => $booking->booking_code,
            'guestName'           => $booking->customer_name,
            'guestEmail'          => $booking->customer_email,
            'cruiseName'          => $booking->schedule->cruise->name ?? 'N/A',
            'departureDate'       => $departureDate,
            'returnDate'          => $returnDate,
            'cabinType'           => $booking->details->first()->cabinClass->name ?? 'N/A',
            'guests'              => $booking->guests ?? 2,
            'totalAmount'         => $displayAmount, 
            'originalPrice'       => (float) $booking->total_price, // Dùng để đối chiếu phí phạt
            'status'              => $booking->status,
            'paymentMethod'       => strtoupper($booking->payment_method ?? 'CASH'),
            'bookedDate'          => $bookedDate,
            'cancellation_reason' => $booking->cancellation_reason,
            'refund_amount'       => (float) $booking->refund_amount,
            'refund_status'       => $booking->refund_status,
        ];
    });

    return response()->json([
        'status' => 'success',
        'data'   => $formattedBookings
    ]);
}

    public function updateBookingStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:holding,confirmed,paid,completed,cancelled']);
        $booking = Booking::findOrFail($id);
        $booking->status = $request->status;
        $booking->save();

        return response()->json(['message' => 'Cập nhật trạng thái thành công', 'data' => $booking]);
    }

    public function processRefund(Request $request, $id)
    {
        try {
            $booking = Booking::findOrFail($id);
            if ($booking->status !== 'cancelled' || $booking->refund_status !== 'pending') {
                return response()->json(['status' => 'error', 'message' => 'Đơn hàng này không chờ hoàn tiền!'], 400);
            }

            if ($request->has('admin_note')) {
                $booking->cancellation_reason = $booking->cancellation_reason . "\n[Kế toán]: " . $request->admin_note;
            }

            $booking->refund_status = 'refund';
            $booking->save();

            return response()->json(['status' => 'success', 'message' => 'Đã xác nhận hoàn tiền cho đơn ' . $booking->booking_code]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

   public function cancelRefundRequest(Request $request, $id)
    {
        try {
            DB::beginTransaction(); // Dùng transaction để đảm bảo an toàn kho phòng
            
            $booking = Booking::findOrFail($id);

            // Kiểm tra trạng thái refund hiện tại
            if (strtolower(trim((string)$booking->refund_status)) !== 'pending') {
                return response()->json(['status' => 'error', 'message' => "Yêu cầu này không ở trạng thái chờ xử lý."], 400);
            }

            // 1. Ghi audit trail (Lý do admin từ chối)
            $adminNote = $request->input('admin_note', 'Admin từ chối yêu cầu hoàn tiền');
            $booking->cancellation_reason = ($booking->cancellation_reason ?? '') 
                . "\n[HỦY YÊU CẦU HOÀN TIỀN - " . now()->format('d/m/Y H:i') . "]: " . $adminNote;

            // 2. KHÔI PHỤC TRẠNG THÁI (Logic mấu chốt ở đây)
            $booking->status = 'paid';           // Trả về trạng thái đã thanh toán bình thường
            $booking->refund_status = null;      // Xóa dấu vết chờ hoàn tiền
            $booking->refund_amount = 0;         // Reset số tiền hoàn
            $booking->cancelled_at = null;       // Xóa ngày hủy đơn

            // 3. CHIẾM LẠI KHO PHÒNG (Quan trọng)
            // Vì khi khách nhấn Hủy, bạn đã gọi releaseRoom(). 
            // Giờ khôi phục đơn thì phải trừ lại số phòng trống trong Schedule.
            $schedule = Schedule::find($booking->schedule_id);
            if ($schedule) {
                foreach ($booking->details as $detail) {
                    $pivot = $schedule->cabin_classes()
                                      ->where('cabin_class_id', $detail->cabin_class_id)
                                      ->first();
                    if ($pivot) {
                        $currentAvailable = $pivot->pivot->available_rooms;
                        // Kiểm tra nếu lúc này tàu đã lỡ bán hết chỗ cho người khác
                        if ($currentAvailable < 1) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error', 
                                'message' => "Không thể khôi phục đơn vì tàu đã hết chỗ (phòng đã được bán cho khách khác sau khi hủy)."
                            ], 400);
                        }
                        
                        // Trừ lại 1 phòng trong kho của lịch trình đó
                        $schedule->cabin_classes()->updateExistingPivot($detail->cabin_class_id, [
                            'available_rooms' => $currentAvailable - 1
                        ]);
                    }
                }
            }

            $booking->save();
            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "Đã từ chối hoàn tiền và khôi phục đơn hàng {$booking->booking_code} về trạng thái Đã thanh toán.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    public function cancelAndRefundBooking(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $booking = Booking::find($id);

            if (!$booking) return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng!'], 404);
            if ($booking->status !== 'paid') return response()->json(['status' => 'error', 'message' => 'Chỉ hỗ trợ hủy đơn đã thanh toán!'], 400);

            $schedule = Schedule::findOrFail($booking->schedule_id);
            $daysUntilDeparture = Carbon::today()->diffInDays(Carbon::parse($schedule->departure_date)->startOfDay(), false);

            $totalPrice = $booking->total_price;
            if ($daysUntilDeparture >= 7) {
                $refundAmount = $totalPrice;
                $cancellationFee = 0;
            } elseif ($daysUntilDeparture >= 3 && $daysUntilDeparture <= 6) {
                $refundAmount = $totalPrice * 0.5;
                $cancellationFee = $totalPrice * 0.5;
            } else {
                $refundAmount = 0;
                $cancellationFee = $totalPrice;
            }

            if (!$booking->releaseRoom()) throw new \Exception("Lỗi hệ thống khi giải phóng kho phòng.");

            $booking->status = 'cancelled';
            $booking->cancellation_reason = "[Admin Xác Nhận Hủy]: " . ($request->reason ?? 'Không có lý do');
            $booking->cancellation_fee = $cancellationFee;
            $booking->refund_amount = $refundAmount;
            $booking->cancelled_at = now();
            if ($refundAmount > 0) $booking->refund_status = 'pending';
            
            $booking->save();
            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Đã hủy đơn hàng!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // =====================================================================
    // 7. QUẢN LÝ LỊCH TRÌNH (SCHEDULES)
    // =====================================================================

    public function getSchedules(Request $request)
    {
        try {
            $query = Schedule::query();
            if ($cruiseId = $request->query('cruise_id')) {
                $query->where('cruise_id', $cruiseId);
            }
            $schedules = $query->orderBy('departure_date', 'desc')->get();

            return response()->json(['status' => 'success', 'data' => $schedules]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSchedule(Request $request)
    {
        try {
            $cruise = Cruise::findOrFail($request->cruise_id);
            $departureDate = Carbon::parse($request->departure_date)->startOfDay();

            if ($departureDate->lt(Carbon::today())) {
                return response()->json(['status' => 'error', 'message' => 'Ngày khởi hành không được ở quá khứ.'], 400);
            }

            if (Schedule::where('cruise_id', $request->cruise_id)->whereDate('departure_date', $departureDate->format('Y-m-d'))->exists()) {
                return response()->json(['status' => 'error', 'message' => "Tàu đã có lịch vào ngày " . $departureDate->format('d/m/Y')], 400);
            }

            $returnDate = $departureDate->copy()->addDays(max(0, $cruise->duration_days - 1));

            $schedule = new Schedule();
            $schedule->cruise_id = $request->cruise_id;
            $schedule->departure_date = $departureDate;
            $schedule->return_date = $returnDate;
            $schedule->status = $request->status ?? 'upcoming';
            $schedule->holiday_id = $request->holiday_id ?? null;
            $schedule->price_factor = $request->price_factor ?? 1.00;
            $schedule->save();

            $cabins = CabinClass::where('cruise_id', $request->cruise_id)->get();
            foreach ($cabins as $cabin) {
                $schedule->cabin_classes()->attach($cabin->id, [
                    'available_rooms' => $cabin->total_rooms ?? $cabin->available_rooms ?? 0
                ]);
            }

            return response()->json(['status' => 'success', 'message' => 'Mở bán thành công!', 'data' => $schedule->load('cabin_classes')]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $hasBookings = Booking::where('schedule_id', $id)->whereNotIn('status', ['cancelled'])->exists();
            $isChangingDates = ($schedule->departure_date != $request->departure_date) || ($schedule->return_date != $request->return_date);

            if ($hasBookings && $isChangingDates) {
                return response()->json(['status' => 'error', 'message' => 'Không thể đổi ngày do đã có khách đặt.'], 400);
            }

            $schedule->departure_date = $request->departure_date;
            $schedule->return_date = $request->return_date;
            $schedule->status = $request->status ?? $schedule->status;
            if ($request->has('holiday_id')) $schedule->holiday_id = $request->holiday_id;
            if ($request->has('price_factor')) $schedule->price_factor = $request->price_factor;
            $schedule->save();

            return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công!', 'data' => $schedule]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSchedule($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            if (Booking::where('schedule_id', $id)->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
                return response()->json(['status' => 'error', 'message' => "Không thể xóa do đã có khách đặt."], 400);
            }
            $schedule->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa lịch trình!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getSchedulesHealth()
    {
        try {
            $schedules = Schedule::with(['cruise', 'cabin_classes'])
                ->whereDate('departure_date', '>=', now()->toDateString())
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->orderBy('departure_date', 'asc')
                ->get();

            $healthData = $schedules->map(function ($schedule) {
                $totalRooms = 0;
                $availableRooms = 0;

                foreach ($schedule->cabin_classes as $cabin) {
                    $totalRooms += $cabin->total_rooms;
                    $availableRooms += $cabin->pivot->available_rooms;
                }

                $bookedRooms = $totalRooms - $availableRooms;
                $occupancyRate = $totalRooms > 0 ? round(($bookedRooms / $totalRooms) * 100, 2) : 0;

                return [
                    'schedule_id' => $schedule->id,
                    'cruise_name' => $schedule->cruise->name ?? 'N/A',
                    'departure_date' => Carbon::parse($schedule->departure_date)->format('d/m/Y'),
                    'return_date' => Carbon::parse($schedule->return_date)->format('d/m/Y'),
                    'status' => $schedule->status,
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
                            'type' => 'Ocean View',
                            'pricePerNight' => (float) $cabin->price,
                            'total_rooms' => $cabin->total_rooms,
                            'available_rooms' => $cabin->pivot->available_rooms,
                        ];
                    })->values()
                ];
            });

            return response()->json(['status' => 'success', 'data' => $healthData]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // =====================================================================
    // 8. DOANH THU & EXCEL
    // =====================================================================

    public function exportRevenueExcel(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $fileName = 'OceanaLux_BaoCaoDoanhThu_' . now()->format('Y_m_d_H_i') . '.xlsx';
        return (new \App\Exports\RevenueExport($startDate, $endDate))->download($fileName);
    }

    public function getRevenueStats(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-01'));
            $endDate = $request->query('end_date', date('Y-m-t'));
            $cruiseId = $request->query('cruise_id', 'all');

            if (strtotime($endDate) < strtotime($startDate)) throw new \Exception("Ngày kết thúc không được nhỏ hơn ngày bắt đầu.");

            $currentQuery = Booking::whereIn('status', ['paid', 'completed', 'confirmed']);
            if ($cruiseId !== 'all') {
                $currentQuery->whereHas('schedule', function($q) use ($cruiseId) { $q->where('cruise_id', $cruiseId); });
            }

            $currentTotal = (clone $currentQuery)->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');
            $currentRecognized = (clone $currentQuery)->where('status', 'completed')->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');
            $currentRefund = Booking::where('status', 'cancelled')->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->sum('total_price');

            $duration = strtotime($endDate) - strtotime($startDate);
            $prevStartDate = date('Y-m-d', strtotime($startDate) - $duration - 86400);
            $prevEndDate = date('Y-m-d', strtotime($startDate) - 86400);
            
            $prevTotal = (clone $currentQuery)->whereBetween('created_at', [$prevStartDate.' 00:00:00', $prevEndDate.' 23:59:59'])->sum('total_price');
            $growth = $prevTotal > 0 ? round((($currentTotal - $prevTotal) / $prevTotal) * 100, 1) : 100;

            $cruisesData = Cruise::withTrashed()->get()->map(function($cruise) use ($startDate, $endDate) {
                $revenue = Booking::whereIn('status', ['paid', 'completed', 'confirmed'])
                    ->whereHas('schedule', function($q) use ($cruise) { $q->where('cruise_id', $cruise->id); })
                    ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->sum('total_price');
                return ['name' => $cruise->name, 'value' => (float)$revenue];
            })->filter(fn($item) => $item['value'] > 0)->values();

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

    // =====================================================================
    // 9. QUẢN LÝ TÀI KHOẢN (ACCOUNTS)
    // =====================================================================

    public function getAccounts()
    {
        $users = User::orderBy('created_at', 'desc')->get()->map(function ($u) {
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
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->password = bcrypt($request->password);
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
            $user = User::findOrFail($id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->role = $request->role;
            
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
            $user = User::findOrFail($id);
            if (Booking::where('user_id', $id)->exists()) {
                return response()->json(['status' => 'error', 'message' => "Không thể xóa do người dùng đã có lịch sử đặt vé."], 400);
            }
            $user->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa tài khoản.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeItinerary(Request $request)
    {
        $request->validate([
            'cruise_id' => 'required|integer',
            'day_number' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'activities' => 'nullable|array',
        ]);

        try {
            $cruise = \App\Models\Cruise::findOrFail($request->cruise_id);
            if ($request->day_number > $cruise->duration_days) {
                return response()->json([
                    'status' => 'error', 
                    'message' => "Không hợp lệ! Tàu {$cruise->name} chỉ có lịch trình tối đa {$cruise->duration_days} ngày."
                ], 400);
            }

            $exists = \App\Models\Itinerary::where('cruise_id', $request->cruise_id)
                                           ->where('day_number', $request->day_number)
                                           ->exists();
            if ($exists) {
                return response()->json(['status' => 'error', 'message' => "Ngày {$request->day_number} đã tồn tại trong lịch trình!"], 400);
            }

            $itinerary = new \App\Models\Itinerary();
            $itinerary->cruise_id = $request->cruise_id;
            $itinerary->day_number = $request->day_number;
            $itinerary->location = $request->location;
            $itinerary->description = $request->description;
            
            // Lưu mảng JSON vào DB
            $itinerary->activities = json_encode($request->activities ?? []); 
            $itinerary->save();
            $itinerary->activities = json_decode($itinerary->activities);

            return response()->json(['status' => 'success', 'message' => 'Đã thêm lịch trình ngày mới!', 'data' => $itinerary]);

        } catch (\Exception $e) {
            // Gom chung 1 chỗ bắt lỗi duy nhất cho toàn bộ hàm
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateItinerary(Request $request, $id)
    {
        $request->validate([
            'day_number' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'description' => 'required|string',
            'activities' => 'nullable|array',
        ]);

        try {
            $itinerary = \App\Models\Itinerary::findOrFail($id);
            $cruise = \App\Models\Cruise::find($itinerary->cruise_id);
            if ($cruise && $request->day_number > $cruise->duration_days) {
                return response()->json([
                    'status' => 'error', 
                    'message' => "Không hợp lệ! Tàu này chỉ cho phép tối đa {$cruise->duration_days} ngày."
                ], 400);
            }
            $exists = \App\Models\Itinerary::where('cruise_id', $itinerary->cruise_id)
                                           ->where('day_number', $request->day_number)
                                           ->where('id', '!=', $id)
                                           ->exists();
            if ($exists) {
                return response()->json(['status' => 'error', 'message' => "Ngày {$request->day_number} đã tồn tại!"], 400);
            }

            $itinerary->day_number = $request->day_number;
            $itinerary->location = $request->location;
            $itinerary->description = $request->description;
            $itinerary->activities = json_encode($request->activities ?? []);
            $itinerary->save();

            $itinerary->activities = json_decode($itinerary->activities);

            return response()->json(['status' => 'success', 'message' => 'Đã cập nhật lịch trình!', 'data' => $itinerary]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteItinerary($id)
    {
        try {
            $itinerary = \App\Models\Itinerary::findOrFail($id);
            $itinerary->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa hoạt động của ngày này!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    // =====================================================================
    // 10. QUẢN LÝ NGÀY LỄ (HOLIDAYS) & GIÁ ĐỘNG
    // =====================================================================

    // API Kiểm tra xem ngày khởi hành có trúng ngày lễ không (Gọi khi Admin chọn lịch)
    public function checkHoliday(Request $request) 
    {
        $date = $request->query('date');
        if (!$date) return response()->json(['is_holiday' => false]);

        $holiday = Holiday::where('is_active', true)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($holiday) {
            return response()->json([
                'is_holiday' => true,
                'holiday_name' => $holiday->name,
                'suggested_multiplier' => (float) $holiday->default_multiplier,
                'holiday_id' => $holiday->id
            ]);
        }

        return response()->json(['is_holiday' => false]);
    }

    // Lấy danh sách ngày lễ
    public function getHolidays()
    {
        try {
            $holidays = Holiday::orderBy('start_date', 'desc')->get();
            return response()->json(['status' => 'success', 'data' => $holidays]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Thêm ngày lễ mới
    public function storeHoliday(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'default_multiplier' => 'required|numeric|min:0.1',
        ]);

        try {
            $holiday = Holiday::create([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'default_multiplier' => $request->default_multiplier,
                'is_active' => $request->is_active ?? true,
                'description' => $request->description,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Đã thêm ngày lễ!', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Cập nhật ngày lễ
    public function updateHoliday(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'default_multiplier' => 'required|numeric|min:0.1',
        ]);

        try {
            $holiday = Holiday::findOrFail($id);
            $holiday->update([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'default_multiplier' => $request->default_multiplier,
                'is_active' => $request->is_active ?? $holiday->is_active,
                'description' => $request->description,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Đã cập nhật ngày lễ!', 'data' => $holiday]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Xóa ngày lễ
    public function deleteHoliday($id)
    {
        try {
            $holiday = Holiday::findOrFail($id);
            $holiday->delete();
            return response()->json(['status' => 'success', 'message' => 'Đã xóa ngày lễ!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}