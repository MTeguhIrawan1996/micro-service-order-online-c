<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Paymentlog;
use Faker\Provider\ar_EG\Payment;
use Illuminate\Http\Request;

class WebhookController extends ApiController
{
    public function midtransHandler(Request $request)
    {
        $data = $request->all();

        $signatureKey = $data['signature_key'];
        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey = env('MIDTRANS_SERVER_KEY');

        $mySignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $transactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];

        if ($signatureKey !== $mySignatureKey) {
            return $this->errorResponse("Invalid signature", 400);
        }

        $realOrderId = explode('-', $orderId);
        $order = Order::find($realOrderId[0]);

        if (!$order) {
            return $this->errorResponse("Order not found", 404);
        }

        if ($order->status === 'success') {
            return $this->errorResponse("operation not pramitted", 405);
        }

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                $order->status = 'success';
            }
        } else if ($transactionStatus == 'settlement') {
            $order->status = 'success';
        } else if (
            $transactionStatus == 'cancel' ||
            $transactionStatus == 'deny' ||
            $transactionStatus == 'expire'
        ) {
            $order->status = 'failure';
        } else if ($transactionStatus == 'pending') {
            $order->status = 'pending';
        }

        $logData = [
            'status' => $transactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $type
        ];

        Paymentlog::create($logData);

        $order->save();

        if ($order->status === 'success') {
            // Akses Premium
            $orderPremium = createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);
        }
        return $this->successResponse(null, $orderPremium['message'], 200);
    }
}