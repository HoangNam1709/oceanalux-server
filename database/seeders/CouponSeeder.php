<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    public function run()
    {
        $coupons = [
            // --- MÃ GIẢM TIỀN MẶT ---
            [
                'code' => 'WELCOME500',
                'title' => 'Bạn mới lên tàu',
                'description' => 'Giảm ngay 500.000đ cho đơn đặt phòng đầu tiên trị giá từ 5 triệu.',
                'discount_amount' => 500000, 'discount_percent' => null,
                'min_order_value' => 5000000, 'usage_limit' => 100,
            ],
            [
                'code' => 'OCEANA1M',
                'title' => 'Ưu đãi hạng sang',
                'description' => 'Giảm 1.000.000đ cho hóa đơn trên 10.000.000đ. Áp dụng toàn hệ thống.',
                'discount_amount' => 1000000, 'discount_percent' => null,
                'min_order_value' => 10000000, 'usage_limit' => 50,
            ],
            [
                'code' => 'VIP2M',
                'title' => 'Đặc quyền VIP',
                'description' => 'Giảm 2.000.000đ cho các đơn hàng lớn từ 20.000.000đ. Trải nghiệm xa hoa.',
                'discount_amount' => 2000000, 'discount_percent' => null,
                'min_order_value' => 20000000, 'usage_limit' => 20,
            ],
            [
                'code' => 'LUXURY5M',
                'title' => 'Giới tinh hoa',
                'description' => 'Siêu ưu đãi giảm 5.000.000đ khi bao trọn tàu hoặc đơn trên 50 triệu.',
                'discount_amount' => 5000000, 'discount_percent' => null,
                'min_order_value' => 50000000, 'usage_limit' => 5,
            ],

            // --- MÃ GIẢM PHẦN TRĂM ---
            [
                'code' => 'EARLYBIRD',
                'title' => 'Đặt sớm giá tốt',
                'description' => 'Giảm 5% cho đơn từ 4 triệu. Áp dụng khi đặt trước 14 ngày.',
                'discount_amount' => null, 'discount_percent' => 5.00,
                'min_order_value' => 4000000, 'usage_limit' => 200,
            ],
            [
                'code' => 'SUMMER10',
                'title' => 'Chào hè rực rỡ',
                'description' => 'Giảm 10% tổng hóa đơn từ 8 triệu. Tha hồ bung xõa với biển xanh.',
                'discount_amount' => null, 'discount_percent' => 10.00,
                'min_order_value' => 8000000, 'usage_limit' => 100,
            ],
            [
                'code' => 'SUPER15',
                'title' => 'Flash Sale Cuối Tuần',
                'description' => 'Giảm cực sâu 15% cho đơn từ 15 triệu. Chỉ có hiệu lực giới hạn.',
                'discount_amount' => null, 'discount_percent' => 15.00,
                'min_order_value' => 15000000, 'usage_limit' => 30,
            ],

            // --- MÃ DÀNH CHO NHÓM ĐỐI TƯỢNG ĐẶC BIỆT ---
            [
                'code' => 'COUPLELOVE',
                'title' => 'Trăng mật ngọt ngào',
                'description' => 'Giảm 800.000đ cho đơn từ 7 triệu. Tặng kèm rượu vang (nếu chọn add-on).',
                'discount_amount' => 800000, 'discount_percent' => null,
                'min_order_value' => 7000000, 'usage_limit' => 50,
            ],
            [
                'code' => 'FAMILYFUN',
                'title' => 'Du lịch Gia Đình',
                'description' => 'Giảm 1.500.000đ khi đi nhóm đông người, đơn tối thiểu 12 triệu.',
                'discount_amount' => 1500000, 'discount_percent' => null,
                'min_order_value' => 12000000, 'usage_limit' => 50,
            ],
            [
                'code' => 'COMPANYTRIP',
                'title' => 'Teambuilding',
                'description' => 'Giảm 8% cho công ty tổ chức Teambuilding, đơn từ 25 triệu.',
                'discount_amount' => null, 'discount_percent' => 8.00,
                'min_order_value' => 25000000, 'usage_limit' => 20,
            ],

            // --- MÃ DÀNH CHO LỄ / SỰ KIỆN ---
            [
                'code' => 'HALONG2026',
                'title' => 'Đón gió Hạ Long',
                'description' => 'Mã đặc biệt giảm 300.000đ cho đơn từ 3 triệu.',
                'discount_amount' => 300000, 'discount_percent' => null,
                'min_order_value' => 3000000, 'usage_limit' => 500,
            ],
            [
                'code' => 'YEAREND',
                'title' => 'Tiệc Tất Niên',
                'description' => 'Giảm 12% để khép lại một năm rực rỡ, đơn từ 18 triệu.',
                'discount_amount' => null, 'discount_percent' => 12.00,
                'min_order_value' => 18000000, 'usage_limit' => 50,
            ],
            [
                'code' => 'BIRTHDAY',
                'title' => 'Sinh Nhật Đại Dương',
                'description' => 'Giảm 600.000đ mừng sinh nhật. Áp dụng cho đơn từ 6.000.000đ.',
                'discount_amount' => 600000, 'discount_percent' => null,
                'min_order_value' => 6000000, 'usage_limit' => 100,
            ],
            [
                'code' => 'NOELBELLS',
                'title' => 'Giáng Sinh Ấm Áp',
                'description' => 'Giảm 7% cho đơn từ 9 triệu dịp Giáng sinh.',
                'discount_amount' => null, 'discount_percent' => 7.00,
                'min_order_value' => 9000000, 'usage_limit' => 80,
            ],
            [
                'code' => 'NEWYEAR',
                'title' => 'Tân Niên Rực Rỡ',
                'description' => 'Lì xì ngay 888.000đ cho đơn đầu năm từ 8.888.000đ.',
                'discount_amount' => 888000, 'discount_percent' => null,
                'min_order_value' => 8888000, 'usage_limit' => 88,
            ],
        ];

        foreach ($coupons as $coupon) {
            $coupon['is_active'] = true;
            $coupon['used_count'] = 0;
            $coupon['starts_at'] = Carbon::now()->subDay(); 
            $coupon['expires_at'] = Carbon::now()->addMonths(6); 

            // Dùng updateOrCreate để đảm bảo KHÔNG BAO GIỜ bị lỗi trùng lặp dữ liệu
            Coupon::updateOrCreate(
                ['code' => $coupon['code']], // Cột để tìm kiếm
                $coupon                      // Dữ liệu sẽ update hoặc tạo mới
            );
        }
    }
}