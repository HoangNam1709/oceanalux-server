<p style="white-space: pre-line;">
Kính chào quý khách {{ $booking->customer_name }},

Cảm ơn quý khách đã tin tưởng và lựa chọn hải trình cùng OceanaLux.

Mã đặt chỗ: {{ $booking->booking_code }}
Du thuyền: {{ $booking->schedule->cruise->name }}
Hạng phòng: {{ $booking->details->first()->cabinClass->name }}
Ngày khởi hành: {{ \Carbon\Carbon::parse($booking->schedule->departure_date)->setTime(8, 0)->format('d/m/Y H:i') }}
Tổng thanh toán: {{ number_format($booking->total_price, 0, ',', '.') }} VNĐ

Trân trọng,
OceanaLux
</p>