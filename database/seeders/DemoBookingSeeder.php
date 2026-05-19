<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoBookingSeeder extends Seeder
{
    public function run()
    {
        // 1. Chỉ lấy những lịch trình có thật và có hạng phòng được gán
        $schedules = Schedule::with('cabin_classes')->get()->filter(function($s) {
            return $s->cabin_classes->isNotEmpty();
        });

        if ($schedules->isEmpty()) {
            $this->command->error('Không tìm thấy lịch trình nào có hạng phòng. Vui lòng tạo Du thuyền và mở lịch bán trước!');
            return;
        }

        // Tìm hoặc tạo 1 User mặc định để gán vào user_id của Booking
        $defaultUser = User::first();
        if (!$defaultUser) {
            $defaultUser = User::create([
                'name' => 'Khách Hàng Demo',
                'email' => 'demo_customer_' . rand(100,999) . '@gmail.com',
                'password' => Hash::make('password123'),
                'role' => 'customer'
            ]);
        }

        $statuses = ['paid', 'completed', 'completed', 'holding', 'cancelled'];
        $paymentMethods = ['vnpay', 'credit_card', 'vnpay'];

        $firstNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng'];
        $middleNames = ['Văn', 'Thị', 'Thanh', 'Minh', 'Hải', 'Ngọc', 'Đức', 'Thu', 'Hoàng'];
        $lastNames = ['An', 'Anh', 'Bình', 'Cường', 'Dương', 'Dũng', 'Hà', 'Huy', 'Khoa', 'Linh', 'Lan', 'Nga', 'Nam', 'Phong', 'Phương', 'Quang', 'Tuấn', 'Thảo', 'Trang', 'Yến'];

        $count = 0;

        for ($i = 0; $i < 12; $i++) {
            $schedule = $schedules->random();
            $cabinClass = $schedule->cabin_classes->random();

            $guests = rand(1, $cabinClass->capacity ?? 2);
            
            $priceFactor = $schedule->price_factor ?? 1.0;
            $basePrice = $cabinClass->price * $priceFactor;
            
            $taxes = 500000 * $guests;
            $totalPrice = $basePrice + $taxes; 

            $status = $statuses[array_rand($statuses)];
            $paymentMethod = $status === 'holding' ? null : $paymentMethods[array_rand($paymentMethods)];

            $name = $firstNames[array_rand($firstNames)] . ' ' . $middleNames[array_rand($middleNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $email = strtolower(Str::slug($name, '')) . rand(100, 999) . '@gmail.com';
            $phone = '0' . rand(3, 9) . rand(10000000, 99999999);

            $createdAt = Carbon::now()->subDays(rand(1, 150))->subHours(rand(1, 23));

            DB::beginTransaction();
            try {
                $booking = Booking::create([
                    'booking_code' => 'BK-' . strtoupper(Str::random(6)),
                    'user_id' => $defaultUser->id,
                    'schedule_id' => $schedule->id,
                    'customer_name' => $name,
                    'customer_email' => $email,
                    'customer_phone' => $phone,
                    'guests' => $guests,
                    'total_price' => $totalPrice,
                    'status' => $status,
                    'payment_method' => $paymentMethod,
                    'transaction_id' => in_array($status, ['paid', 'completed']) ? 'VNP' . rand(10000000, 99999999) : null,
                    'cancellation_reason' => $status === 'cancelled' ? '[Khách hàng]: Hủy do việc bận đột xuất' : null,
                    'refund_amount' => $status === 'cancelled' ? $totalPrice * 0.5 : 0,
                    'refund_status' => $status === 'cancelled' ? 'refund' : null,
                    'cancelled_at' => $status === 'cancelled' ? $createdAt->copy()->addDays(1) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                BookingDetail::create([
                    'booking_id' => $booking->id,
                    'cabin_class_id' => $cabinClass->id,
                    'price' => $basePrice,
                    'quantity' => 1, 
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                if ($status !== 'cancelled') {
                    $pivot = $schedule->cabin_classes()->where('cabin_class_id', $cabinClass->id)->first();
                    if ($pivot && $pivot->pivot->available_rooms > 0) {
                        $schedule->cabin_classes()->updateExistingPivot($cabinClass->id, [
                            'available_rooms' => $pivot->pivot->available_rooms - 1
                        ]);
                    }
                }

                DB::commit();
                $count++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("Lỗi tạo đơn: " . $e->getMessage());
            }
        }

        $this->command->info("🎉 THÀNH CÔNG! Đã bơm {$count} đơn đặt chỗ demo vào hệ thống (Chỉ thêm, không xóa đơn cũ).");
    }
}