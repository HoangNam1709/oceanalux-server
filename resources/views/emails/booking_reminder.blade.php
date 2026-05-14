<p style="white-space: pre-line;">
Kính chào quý khách {{ $booking->customer_name }},

Chỉ còn 48 giờ nữa, hải trình nghỉ dưỡng của quý khách cùng OceanaLux sẽ chính thức bắt đầu!

Thông tin chuyến đi:
Mã đặt chỗ: {{ $booking->booking_code }}
Du thuyền: {{ $booking->schedule->cruise->name }}
Hạng phòng: {{ $booking->details->first()->cabinClass->name }}
Ngày khởi hành: {{ \Carbon\Carbon::parse($booking->schedule->departure_date)->format('d/m/Y 08:00') }}

Lưu ý quan trọng:
- Quý khách vui lòng mang theo đầy đủ giấy tờ tùy thân (CCCD/Passport bản gốc) của tất cả hành khách để làm thủ tục nhận phòng.
- Vui lòng có mặt tại nhà chờ OceanaLux trước giờ khởi hành ít nhất 45 phút.
- Nếu có yêu cầu đặc biệt về chế độ ăn uống, xin vui lòng phản hồi lại email này.

Đội ngũ OceanaLux rất hân hạnh được đón tiếp quý khách. Chúc quý khách có một chuyến đi tuyệt vời!

Trân trọng,
OceanaLux
</p>