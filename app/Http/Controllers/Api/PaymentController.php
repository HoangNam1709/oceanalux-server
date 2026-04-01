<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // Thông tin tài khoản chuẩn
    private $vnp_TmnCode = "G5IH7EKY";
    private $vnp_HashSecret = "S6RMKH4YKVVV9FY9LI4LICUGW9I50NMO";
    private $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    private $vnp_Returnurl = "http://localhost:5173/payment-result"; // Tuyệt đối không có dấu / ở cuối

    /**
     * BƯỚC 1: TẠO REQUEST THANH TOÁN
     */
    public function createPayment(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $method = $request->input('payment_method');
        $frontendAmount = $request->input('amount');
        if (!$bookingId) {
            return response()->json(['message' => 'Thiếu ID đơn hàng'], 400);
        }

        $booking = Booking::where('id', $bookingId)
            ->where('status', 'holding')
            ->firstOrFail();
// Nhưng tạm thời để test chạy mượt đồ án, bạn cập nhật luôn số tiền từ React vào:
        if ($frontendAmount && $frontendAmount > $booking->total_price) {
            $booking->update([
                'total_price' => $frontendAmount
            ]);
            // Cập nhật lại giá trị biến booking để VNPAY lấy đúng tiền
            $booking = $booking->fresh(); 
        }
        // Xử lý thanh toán tiền mặt
        if ($method === 'cash') {
            $booking->update([
                'status' => 'paid',
                'payment_method' => 'cash',
                'transaction_id' => 'CASH_TEST_' . time()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Thanh toán tiền mặt thành công (Test Mode)'
            ]);
        }

        // Cấu hình tham số VNPay
        $vnp_TxnRef = $booking->booking_code; 
        $vnp_OrderInfo = "Thanh_toan_don_hang_" . $booking->booking_code; // Không dùng dấu cách
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = round($booking->total_price * 100); 
        $vnp_Locale = 'vn';
        
        // Chuẩn hóa IP
        $vnp_IpAddr = $request->ip();
        if ($vnp_IpAddr == '::1' || $vnp_IpAddr == '127.0.0.1') {
             $vnp_IpAddr = '127.0.0.1';
        }

        date_default_timezone_set('Asia/Ho_Chi_Minh');

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $this->vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $this->vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef
        );

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";

        // CHUẨN HÓA MÃ HÓA (Đồng bộ cho cả 3 hàm)
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $this->vnp_Url . "?" . $query;

        if (isset($this->vnp_HashSecret)) {
            $cleanSecret = trim($this->vnp_HashSecret); 
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $cleanSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return response()->json([
            'status' => 'success',
            'checkoutUrl' => $vnp_Url
        ]);
    }

    /**
     * BƯỚC 2: IPN WEBHOOK (Cập nhật Database ngầm)
     */
    public function vnpayIpn(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        
        // Xóa các tham số hash ra khỏi mảng trước khi tính toán lại
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']); 
        
        ksort($inputData);
        $i = 0;
        $hashData = "";
        
        // Vòng lặp phải giống hệt lúc tạo request
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $cleanSecret = trim($this->vnp_HashSecret);
        $secureHash = hash_hmac('sha512', $hashData, $cleanSecret);
        
        try {
            if ($secureHash == $vnp_SecureHash) {
                $booking = Booking::where('booking_code', $inputData['vnp_TxnRef'])->first();

                if ($booking != NULL) {
                    // Fix lỗi làm tròn số thập phân khi so sánh tiền
                    if (round($booking->total_price * 100) == $inputData['vnp_Amount']) {
                        if ($booking->status == 'holding') {
                            if ($inputData['vnp_ResponseCode'] == '00') {
                                $booking->update([
                                    'status' => 'paid',
                                    'payment_method' => 'vnpay',
                                    'transaction_id' => $inputData['vnp_TransactionNo']
                                ]);
                                return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
                            } else {
                                $booking->update(['status' => 'cancelled']);
                                return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
                            }
                        }
                        return response()->json(['RspCode' => '02', 'Message' => 'Order already confirmed']);
                    }
                    return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
                }
                return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
            }
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        } catch (\Exception $e) {
            Log::error("VNPay IPN Error: " . $e->getMessage());
            return response()->json(['RspCode' => '99', 'Message' => 'Unknown error']);
        }
    }

    /**
     * BƯỚC 3: RETURN URL (Trả về giao diện Frontend)
     */
    public function vnpayReturn(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']);
        
        ksort($inputData);
        $i = 0;
        $hashData = "";
        
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $cleanSecret = trim($this->vnp_HashSecret);
        $secureHash = hash_hmac('sha512', $hashData, $cleanSecret);

        if ($secureHash == $vnp_SecureHash) {
            if ($request->vnp_ResponseCode == '00') {
                return view('payment.success', ['message' => 'Giao dịch thành công!']);
            }
            return view('payment.failed', ['message' => 'Giao dịch không thành công hoặc đã bị hủy.']);
        }
        return view('payment.failed', ['message' => 'Chữ ký không hợp lệ!']);
    }
}