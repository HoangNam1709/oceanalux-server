<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\BookingSuccessMail;

class PaymentController extends Controller
{
    // Thông tin tài khoản chuẩn
    private $vnp_TmnCode = "G5IH7EKY";
    private $vnp_HashSecret = "S6RMKH4YKVVV9FY9LI4LICUGW9I50NMO";
    private $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    private $vnp_Returnurl = "http://localhost:5173/payment-result"; 
     //BƯỚC 1: TẠO REQUEST THANH TOÁN
    public function createPayment(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $method = $request->input('payment_method');
        $frontendAmount = $request->input('amount');
        
        if (!$bookingId) {
            return response()->json(['message' => 'Thiếu ID đơn hàng'], 400);
        }

        // 1. TÌM ĐƠN HÀNG
        $booking = Booking::where('id', $bookingId)
            ->whereIn('status', ['holding', 'cancelled']) // Vẫn cho phép thanh toán lại đơn lỡ hủy
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Đơn hàng không tồn tại hoặc đã bị xử lý'], 404);
        }

        // KIỂM TRA THỜI HẠN 
        if ($booking->hold_expires_at && now()->greaterThan($booking->hold_expires_at)) {
            if ($booking->status === 'holding') {
                $booking->update(['status' => 'cancelled']);
            }
            return response()->json(['message' => 'Đơn hàng đã quá hạn giữ chỗ! Vui lòng đặt lại.'], 400);
        }

        // Cập nhật số tiền từ React
        if ($frontendAmount && $frontendAmount > $booking->total_price) {
            $booking->update([
                'total_price' => $frontendAmount
            ]);
            $booking = $booking->fresh(); 
        }

        // KIỂM TRA SỐ TIỀN HỢP LỆ
        if ($booking->total_price <= 0) {
            return response()->json(['message' => 'Số tiền thanh toán không hợp lệ.'], 400);
        }

        // FIX 2: Nối đuôi timestamp để tránh VNPAY báo lỗi trùng mã giao dịch (Error.html)
        $vnp_TxnRef = $booking->booking_code . '_' . time();
        $vnp_OrderInfo = "Thanh_toan_don_hang_" . $booking->booking_code; 
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

        // CHUẨN HÓA MÃ HÓA
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
     //BƯỚC 2: IPN WEBHOOK (Cập nhật Database ngầm)
    public function vnpayIpn(Request $request)
    {
        $inputData = [];
        
        // BẢO MẬT 1: Lọc rác
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

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
        
        try {
            // BẢO MẬT 2: Dùng hash_equals
            if (hash_equals($secureHash, $vnp_SecureHash)) {
                
                $vnp_TxnRef = $inputData['vnp_TxnRef'];
                $realBookingCode = explode('_', $vnp_TxnRef)[0]; 
                
                $booking = Booking::where('booking_code', $realBookingCode)->first();
                
                if ($booking != NULL) {
                    if ((int)round($booking->total_price * 100) == (int)$inputData['vnp_Amount']) {
                        
                        // FIX 3: Chấp nhận xử lý nếu đơn đang Holding hoặc Cancelled
                        if (in_array($booking->status, ['holding', 'cancelled'])) {
                            
                            // Giao dịch thành công (00)
                            if ($inputData['vnp_ResponseCode'] == '00') {
                                $booking->update([
                                    'status' => 'paid',
                                    'payment_method' => 'vnpay',
                                    'transaction_id' => $inputData['vnp_TransactionNo']
                                ]);

                                // 👉 GỬI EMAIL XÁC NHẬN NGAY KHI THANH TOÁN XONG
                                try {
                                    // Lấy đầy đủ data (tàu, cabin) để render ra HTML email không bị lỗi null
                                    $bookingWithDetails = Booking::with(['schedule.cruise', 'details.cabinClass'])->find($booking->id);
                                    
                                    Mail::to($bookingWithDetails->customer_email)->send(new BookingSuccessMail($bookingWithDetails));
                                    Log::info("Đã gửi email vé điện tử thành công cho đơn hàng: " . $booking->booking_code);
                                } catch (\Exception $e) {
                                    Log::error("Lỗi gửi email cho đơn {$booking->booking_code}: " . $e->getMessage());
                                }

                            } 
                            // Giao dịch thất bại / Bị hủy
                            else {
                                if ($booking->status == 'holding') {
                                    $booking->update(['status' => 'cancelled']);
                                }
                            }
                            
                            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
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

    //BƯỚC 3: RETURN URL (Trả về giao diện React)
    public function vnpayReturn(Request $request)
    {
        $inputData = [];
        
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

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

        if (hash_equals($secureHash, $vnp_SecureHash)) {
            
            // 🚀 BƯỚC QUAN TRỌNG: Lấy mã đơn hàng gốc
            $vnp_TxnRef = $request->vnp_TxnRef;
            $realBookingCode = explode('_', $vnp_TxnRef)[0]; 
            
            $booking = Booking::where('booking_code', $realBookingCode)->first();

            if (!$booking) {
                return redirect('http://localhost:5173/dashboard?payment=error');
            }

            if ($request->vnp_ResponseCode == '00') {
                // Cập nhật Database ngay tại đây nếu IPN chưa kịp chạy
                if ($booking->status === 'holding') {
                    $booking->update([
                        'status' => 'paid',
                        'payment_method' => 'vnpay',
                        'transaction_id' => $request->vnp_TransactionNo,
                        'hold_expires_at' => null // Xóa hẹn giờ
                    ]);
                }
                // Điều hướng thẳng về Trang Giao dịch Thành công (React)
                return redirect('http://localhost:5173/checkout/payment/'.$booking->id.'?status=success');
            }
            
            return redirect('http://localhost:5173/checkout/payment/'.$booking->id.'?status=failed');
        }
        
        return redirect('http://localhost:5173/dashboard?payment=invalid_signature');
    }
    // Thêm hàm này vào PaymentController
    public function verifyPayment(Request $request)
    {
        $inputData = [];
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }

        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']);
        
        ksort($inputData);
        $hashData = "";
        $i = 0;
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

        if (hash_equals($secureHash, $vnp_SecureHash)) {

            $vnp_TxnRef = $request->vnp_TxnRef;
            $realBookingCode = explode('_', $vnp_TxnRef)[0]; 
            
            $booking = Booking::where('booking_code', $realBookingCode)->first();

            if (!$booking) {
                return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng']);
            }

            if ($request->vnp_ResponseCode == '00') {
                if ($booking->status === 'holding') {
                    $booking->update([
                        'status' => 'paid',
                        'payment_method' => 'vnpay',
                        'transaction_id' => $request->vnp_TransactionNo,
                        'hold_expires_at' => null
                    ]);
                    $this->sendBookingSuccessMail($booking->id);
                }
                return response()->json(['status' => 'success', 'booking_id' => $booking->id]);
            }
            return response()->json(['status' => 'failed', 'booking_id' => $booking->id]);
        }
        return response()->json(['status' => 'error', 'message' => 'Chữ ký bảo mật không hợp lệ']);
    }
    private function sendBookingSuccessMail($bookingId)
{
    try {
        $booking = Booking::with(['schedule.cruise', 'details.cabinClass'])->find($bookingId);
        if ($booking) {
            Mail::to($booking->customer_email)->send(new BookingSuccessMail($booking));
            Log::info("Mail success sent to: " . $booking->customer_email);
        }
    } catch (\Exception $e) {
        Log::error("Mail fail: " . $e->getMessage());
    }
}
}