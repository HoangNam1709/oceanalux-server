<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private $vnp_TmnCode = "GOR8HYLI";
    private $vnp_HashSecret = "FFWHYT9VEQN2YRFLYOFJ5927MNL6K7EJ";
    private $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    private $vnp_Returnurl = "https://unshirking-famishedly-miracle.ngrok-free.dev/payment/vnpay/return";

    /**
     * BƯỚC 1: TẠO REQUEST THANH TOÁN
     */
    public function createPayment(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $method = $request->input('payment_method');

        if (!$bookingId) {
            return response()->json(['message' => 'Thiếu ID đơn hàng'], 400);
        }

        // 1. TÌM ĐƠN HÀNG TRƯỚC (Quan trọng: Phải tìm trước khi sử dụng biến $booking)
        $booking = Booking::where('id', $bookingId)
            ->where('status', 'holding')
            ->firstOrFail();

        // 2. XỬ LÝ THANH TOÁN TIỀN MẶT (TEST MODE)
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

        // 3. XỬ LÝ VNPAY
        $vnp_TxnRef = $booking->booking_code; 
        $vnp_OrderInfo = "Thanh toan don hang " . $booking->booking_code;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $booking->total_price * 100; 
        $vnp_Locale = 'vn';
        $vnp_IpAddr = $request->ip();

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
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $this->vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        // Trả về link VNPay. Lưu ý: Dùng key 'checkoutUrl' để khớp với CheckoutPage.tsx
        return response()->json([
            'status' => 'success',
            'checkoutUrl' => $vnp_Url
        ]);
    }

    /**
     * BƯỚC 2: IPN WEBHOOK
     */
    public function vnpayIpn(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);
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

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);
        
        try {
            if ($secureHash == $vnp_SecureHash) {
                $booking = Booking::where('booking_code', $inputData['vnp_TxnRef'])->first();

                if ($booking != NULL) {
                    if ($booking->total_price * 100 == $inputData['vnp_Amount']) {
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
     * BƯỚC 3: RETURN URL
     */
    public function vnpayReturn(Request $request)
    {
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']);
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

        $secureHash = hash_hmac('sha512', $hashData, $this->vnp_HashSecret);

        if ($secureHash == $vnp_SecureHash) {
            if ($request->vnp_ResponseCode == '00') {
                return view('payment.success', ['message' => 'Giao dịch thành công!']);
            }
            return view('payment.failed', ['message' => 'Giao dịch không thành công hoặc đã bị hủy.']);
        }
        return view('payment.failed', ['message' => 'Chữ ký không hợp lệ!']);
    }
}