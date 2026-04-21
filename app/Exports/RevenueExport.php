<?php

namespace App\Exports;

use App\Models\Booking;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class RevenueExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    use Exportable;

    protected $startDate;
    protected $endDate;

    // Cho phép truyền ngày tháng để lọc báo cáo linh hoạt
    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }


     //1. XỬ LÝ DỮ LIỆU BẰNG QUERY BUIDLER (Chống sập RAM)
    public function query()
    {
        $query = Booking::query()->with(['schedule.cruise', 'details.cabinClass'])
            // CHỈ LẤY CÁC ĐƠN ĐÃ CHỐT TIỀN (Không tính đơn nháp, đơn hủy)
            ->whereIn('status', ['paid', 'completed', 'confirmed']);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        return $query->orderBy('created_at', 'desc');
    }

     //2. TIÊU ĐỀ CỘT
    public function headings(): array
    {
        return [
            'Mã Đơn',
            'Ngày Đặt',
            'Tên Khách Hàng',
            'Email',
            'Du Thuyền',
            'Hạng Phòng',
            'Số Khách',
            'Doanh Thu (VNĐ)',
            'Thanh Toán',
            'Trạng Thái'
        ];
    }

    //3. XỬ LÝ LOGIC TỪNG DÒNG (MAP DATA)
    public function map($booking): array
    {
        $statusMap = [
            'paid' => 'Đã thanh toán',
            'completed' => 'Hoàn thành'
        ];

        return [
            $booking->booking_code ?? 'N/A',
            $booking->created_at ? $booking->created_at->format('d/m/Y H:i') : '',
            $booking->customer_name,
            $booking->customer_email,
            $booking->schedule->cruise->name ?? 'Chưa rõ tàu',
            optional(optional($booking->details->first())->cabinClass)->name ?? 'Chưa rõ phòng',
            $booking->guests ?? 2,
            
            // Ép kiểu số thực để Excel hiểu đây là số (không phải text), từ đó dùng hàm SUM() được
            (float) $booking->total_price, 
            
            strtoupper($booking->payment_method ?? 'CASH'),
            $statusMap[$booking->status] ?? $booking->status
        ];
    }

     //4. FORMAT TIỀN TỆ TRONG EXCEL
    public function columnFormats(): array
    {
        return [
            // Cột H là cột "Doanh Thu" -> Format số có dấu phẩy: 1,500,000
            'H' => '#,##0_-',
        ];
    }

     //5. STYLING (Lên đồ hàng hiệu cho File Excel)
    public function styles(Worksheet $sheet)
    {
        // Định dạng Dòng 1 (Header)
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFD4AF37'], // Vàng Champagne Gold
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0A192F'], // Xanh Deep Navy
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['argb' => 'FFD4AF37'],
                ],
            ]
        ]);

        // Căn giữa các cột mã đơn, ngày, số khách, thanh toán, trạng thái
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('B:B')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('G:G')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('I:J')->getAlignment()->setHorizontal('center');

        return [
            // Freeze pane: Cố định dòng tiêu đề để khi cuộn chuột xuống vẫn thấy
            1    => ['font' => ['bold' => true]],
        ];
    }
}