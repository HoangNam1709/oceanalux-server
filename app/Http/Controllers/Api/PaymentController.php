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
    private string $vnp_TmnCode = 'LGY34V8D';
    private string $vnp_HashSecret = 'WV3SK5PFDOE0CEP52U3YMA99UM9G9UQW';
    private string $vnp_Url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';

    // Giữ return về frontend như ban đầu
    private string $vnp_Returnurl = 'http://localhost:5173/payment-result';

    private function buildHashData(array $data): string
{
    ksort($data);

    $hashData = [];
    foreach ($data as $key => $value) {
        if ($value !== null && $value !== '') {
            $hashData[] = urlencode($key) . '=' . urlencode($value);
        }
    }

    return implode('&', $hashData);
}

    private function buildQueryString(array $data): string
    {
        ksort($data);

        $query = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $query[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return implode('&', $query);
    }

    private function generateSignature(string $hashData): string
    {
        return hash_hmac('sha512', $hashData, trim($this->vnp_HashSecret));
    }

    private function verifySignature(array $inputData, string $vnpSecureHash): bool
    {
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']);

        $hashData = $this->buildHashData($inputData);
        $signature = $this->generateSignature($hashData);

        Log::debug('[VNPAY VERIFY] hashData: ' . $hashData);
        Log::debug('[VNPAY VERIFY] mySignature: ' . $signature);
        Log::debug('[VNPAY VERIFY] vnpSecureHash: ' . $vnpSecureHash);

        return hash_equals($signature, $vnpSecureHash);
    }

    private function sendBookingSuccessMail(int $bookingId): void
    {
        try {
            $booking = Booking::with(['schedule.cruise', 'details.cabinClass'])
                ->find($bookingId);

            if ($booking) {
                Mail::to($booking->customer_email)->send(new BookingSuccessMail($booking));
                Log::info("[VNPAY] Gửi vé điện tử thành công: {$booking->booking_code}");
            }
        } catch (\Exception $e) {
            Log::error("[VNPAY] Gửi mail thất bại booking #{$bookingId}: {$e->getMessage()}");
        }
    }

public function createPayment(Request $request)
    {
        $bookingId = $request->input('booking_id');
        
        // 🚀 BƯỚC 1: Lấy số tiền grandTotal từ React gửi lên
        $frontendAmount = $request->input('amount'); 

        if (!$bookingId) {
            return response()->json(['message' => 'Thiếu ID đơn hàng'], 400);
        }

        $booking = Booking::where('id', $bookingId)
            ->whereIn('status', ['holding', 'pending'])
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'Đơn hàng không tồn tại hoặc đã được xử lý'
            ], 404);
        }

        if ($booking->hold_expires_at && now()->greaterThan($booking->hold_expires_at)) {
            $booking->update(['status' => 'cancelled']);
            return response()->json([
                'message' => 'Đơn hàng đã quá hạn giữ chỗ! Vui lòng đặt lại.'
            ], 400);
        }

        // 🚀 BƯỚC 2: Cập nhật số tiền khớp 100% với Frontend trước khi sang VNPAY
        if ($frontendAmount && $frontendAmount > 0) {
            $booking->update([
                'total_price' => $frontendAmount
            ]);
            $booking->refresh(); // Load lại DB để lấy giá mới nhất
        }

        if ($booking->total_price <= 0) {
            return response()->json([
                'message' => 'Số tiền thanh toán không hợp lệ.'
            ], 400);
        }

        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $vnp_TxnRef = $booking->booking_code . '_' . time();

        // 🚀 BƯỚC 3: Tuyệt đối không gửi 127.0.0.1 sang VNPAY để tránh Lỗi 72
        $rawIp = $request->ip();
        if ($rawIp == '127.0.0.1' || $rawIp == '::1') {
            $vnp_IpAddr = '42.112.63.236'; // Cắm cứng IP Public của bạn
        } else {
            $vnp_IpAddr = $rawIp;
        }

        $createDate = date('YmdHis');
        $expireDate = date('YmdHis', strtotime('+15 minutes'));

        $inputData = [
            'vnp_Version'    => '2.1.0',
            'vnp_TmnCode'    => $this->vnp_TmnCode,
            'vnp_Amount'     => sprintf('%.0f', $booking->total_price * 100), 
            'vnp_Command'    => 'pay',
            'vnp_CreateDate' => $createDate,
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => $vnp_IpAddr,
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toan don hang ' . $booking->booking_code,
            'vnp_OrderType'  => 'other',
            'vnp_ReturnUrl'  => $this->vnp_Returnurl,
            'vnp_TxnRef'     => $vnp_TxnRef,
            'vnp_ExpireDate' => $expireDate,
        ];

        $hashData = $this->buildHashData($inputData);
        $query = $this->buildQueryString($inputData);
        $signature = $this->generateSignature($hashData);

        $checkoutUrl = $this->vnp_Url . '?' . $query . '&vnp_SecureHash=' . $signature;

        Log::debug("[VNPAY] createPayment — TxnRef: {$vnp_TxnRef}");
        Log::debug("[VNPAY] IpAddr gốc: {$rawIp} → dùng: {$vnp_IpAddr}");
        Log::debug("[VNPAY] checkoutUrl: {$checkoutUrl}");

        return response()->json([
            'status' => 'success',
            'checkoutUrl' => $checkoutUrl,
        ]);
    }

    public function vnpayIpn(Request $request)
    {
        $inputData = collect($request->all())
            ->filter(fn ($value, $key) => str_starts_with($key, 'vnp_'))
            ->toArray();

        $vnpSecureHash = $inputData['vnp_SecureHash'] ?? '';

        try {
            if (!$this->verifySignature($inputData, $vnpSecureHash)) {
                return response()->json([
                    'RspCode' => '97',
                    'Message' => 'Invalid signature'
                ]);
            }

            $realBookingCode = explode('_', $inputData['vnp_TxnRef'])[0] ?? null;

            $booking = Booking::where('booking_code', $realBookingCode)->first();

            if (!$booking) {
                return response()->json([
                    'RspCode' => '01',
                    'Message' => 'Order not found'
                ]);
            }

            if ((int) round($booking->total_price * 100) !== (int) $inputData['vnp_Amount']) {
                return response()->json([
                    'RspCode' => '04',
                    'Message' => 'Invalid amount'
                ]);
            }

            if (!in_array($booking->status, ['holding', 'pending'])) {
                return response()->json([
                    'RspCode' => '02',
                    'Message' => 'Order already confirmed'
                ]);
            }

            if (($inputData['vnp_ResponseCode'] ?? '') === '00') {
                $booking->update([
                    'status' => 'paid',
                    'payment_method' => 'vnpay',
                    'transaction_id' => $inputData['vnp_TransactionNo'] ?? null,
                    'hold_expires_at' => null,
                ]);

                $this->sendBookingSuccessMail($booking->id);

                Log::info("[VNPAY IPN] Thanh toán thành công: {$booking->booking_code}");
            } else {
                $booking->update(['status' => 'cancelled']);

                Log::warning("[VNPAY IPN] Giao dịch thất bại: {$booking->booking_code}");
            }

            return response()->json([
                'RspCode' => '00',
                'Message' => 'Confirm Success'
            ]);
        } catch (\Exception $e) {
            Log::error("[VNPAY IPN] Exception: {$e->getMessage()}");

            return response()->json([
                'RspCode' => '99',
                'Message' => 'Unknown error'
            ]);
        }
    }

    public function vnpayReturn(Request $request)
    {
        $inputData = collect($request->all())
            ->filter(fn ($value, $key) => str_starts_with($key, 'vnp_'))
            ->toArray();

        $vnpSecureHash = $inputData['vnp_SecureHash'] ?? '';

        if (!$this->verifySignature($inputData, $vnpSecureHash)) {
            Log::warning('[VNPAY Return] Sai chữ ký');

            return redirect('http://localhost:5173/dashboard?payment=invalid_signature');
        }

        $realBookingCode = explode('_', $request->vnp_TxnRef)[0] ?? null;

        $booking = Booking::where('booking_code', $realBookingCode)->first();

        if (!$booking) {
            return redirect('http://localhost:5173/dashboard?payment=error');
        }

        if ($request->vnp_ResponseCode === '00') {
            if (in_array($booking->status, ['holding', 'pending'])) {
                $booking->update([
                    'status' => 'paid',
                    'payment_method' => 'vnpay',
                    'transaction_id' => $request->vnp_TransactionNo,
                    'hold_expires_at' => null,
                ]);

                $this->sendBookingSuccessMail($booking->id);
            }

            return redirect("http://localhost:5173/checkout/payment/{$booking->id}?status=success");
        }

        return redirect("http://localhost:5173/checkout/payment/{$booking->id}?status=failed");
    }

    public function verifyPayment(Request $request)
    {
        $inputData = collect($request->all())
            ->filter(fn ($value, $key) => str_starts_with($key, 'vnp_'))
            ->toArray();

        $vnpSecureHash = $inputData['vnp_SecureHash'] ?? '';

        if (!$this->verifySignature($inputData, $vnpSecureHash)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký bảo mật không hợp lệ'
            ]);
        }

        $realBookingCode = explode('_', $request->vnp_TxnRef)[0] ?? null;

        $booking = Booking::where('booking_code', $realBookingCode)->first();

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy đơn hàng'
            ]);
        }

        if ($request->vnp_ResponseCode === '00') {
            if (in_array($booking->status, ['holding', 'pending'])) {
                $booking->update([
                    'status' => 'paid',
                    'payment_method' => 'vnpay',
                    'transaction_id' => $request->vnp_TransactionNo,
                    'hold_expires_at' => null,
                ]);

                $this->sendBookingSuccessMail($booking->id);
            }

            return response()->json([
                'status' => 'success',
                'booking_id' => $booking->id
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'booking_id' => $booking->id
        ]);
    }
}