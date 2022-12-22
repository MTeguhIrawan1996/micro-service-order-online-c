<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Str;

class OrderController extends ApiController
{
    public function index(Request $request)
    {
        $userId = $request->query('user_id');

        $orders = Order::query();

        $orders->when($userId, function ($query) use ($userId) {
            return $query->where('user_id', "=", $userId);
        });


        return $this->successResponse($orders->get(), "Get Order success", 200);
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        $isExistMyOrder = Order::where("course_id", "=", $course['id'])->where("user_id", "=", $user['id'])->where('status', '=', 'pending')->exists();
        if ($isExistMyOrder) {
            return $this->errorResponse("The user has an unfinished payment", 409);
        }

        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id']
        ]);

        $transactionDetails = [
            'order_id' => $order->id . '-' . Str::random(5),
            'gross_amount' => $course['price']
        ];

        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => $course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'OneDev',
                'category' => 'Online Course'
            ]
        ];

        $customerDetails = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];

        $midtransParams = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails
        ];

        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);


        $order->snap_url = $midtransSnapUrl;

        $order->metadata = [
            'course_id' => $course['id'],
            'course_price' => $course['price'],
            'course_name' => $course['name'],
            'course_thumbnail' => $course['thumbnail'],
            'course_level' => $course['level']
        ];

        $order->save();

        return $this->successResponse($order, "Create Order success", 200);
    }


    private function getMidtransSnapUrl($params)
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_3DS');

        $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;

        return $snapUrl;
    }
}