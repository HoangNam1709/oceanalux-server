<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vé Điện Tử OceanaLux</title>
    <style>
        /* Bắt buộc dùng DejaVu Sans để không lỗi font tiếng Việt */
        body { font-family: 'DejaVu Sans', sans-serif; color: #333; line-height: 1.5; }
        .ticket-container { border: 2px solid #0A192F; border-radius: 10px; padding: 20px; margin: 0 auto; max-width: 100%; }
        .header { text-align: center; border-bottom: 2px solid #D4AF37; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { color: #0A192F; margin: 0; font-size: 24px; text-transform: uppercase; }
        .header p { color: #666; margin: 5px 0 0 0; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        td { padding: 8px 0; vertical-align: top; }
        .label { font-weight: bold; color: #0A192F; width: 40%; }
        .value { color: #333; }
        .price { color: #D4AF37; font-size: 18px; font-weight: bold; }
        .qr-section { text-align: center; margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 20px; }
        .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #888; }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="header">
            <h1>OceanaLux Cruises</h1>
            <p>VÉ ĐIỆN TỬ / E-TICKET</p>
        </div>

        <table>
            <tr>
                <td class="label">Mã đặt chỗ:</td>
                <td class="value" style="color: #D4AF37; font-weight: bold; font-size: 18px;">{{ $booking->booking_code }}</td>
            </tr>
            <tr>
                <td class="label">Hành khách:</td>
                <td class="value">{{ $booking->customer_name }}</td>
            </tr>
            <tr>
                <td class="label">Số điện thoại:</td>
                <td class="value">{{ $booking->customer_phone ?? 'Chưa cập nhật' }}</td>
            </tr>
            <tr>
                <td class="label">Du thuyền:</td>
                <td class="value">{{ $booking->schedule->cruise->name ?? 'OceanaLux Cruise' }}</td>
            </tr>
            <tr>
                <td class="label">Hạng phòng:</td>
                <td class="value">{{ $booking->details->first()->cabinClass->name ?? 'Standard' }}</td>
            </tr>
            <tr>
                <td class="label">Khởi hành:</td>
                <td class="value">{{ \Carbon\Carbon::parse($booking->schedule->departure_time)->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="label">Tổng thanh toán:</td>
                <td class="value price">{{ number_format($booking->total_price, 0, ',', '.') }} VNĐ</td>
            </tr>
            <tr>
                <td class="label">Trạng thái:</td>
                <td class="value" style="color: green; font-weight: bold;">ĐÃ THANH TOÁN</td>
            </tr>
        </table>

        <div class="qr-section">
            <p style="font-weight: bold; margin-bottom: 10px;">MÃ QR XÁC NHẬN (Quét khi lên tàu)</p>
            <img src="data:image/png;base64, {!! base64_encode(QrCode::format('png')->size(150)->generate('OCEANALUX-' . $booking->booking_code . '-' . $booking->id)) !!} ">
        </div>

        <div class="footer">
            <p>Vui lòng mang theo CMND/CCCD/Hộ chiếu để làm thủ tục nhận phòng.<br>
            Cảm ơn quý khách đã lựa chọn OceanaLux Cruises.</p>
        </div>
    </div>
</body>
</html>