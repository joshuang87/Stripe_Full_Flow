<?php

namespace App\Http\Controllers;

use Error;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();

        return view('product.index',compact('products'));
    }

    public function checkout()
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

        $products = Product::all();

        $lineItems = [];
        $totalPrice = 0;
        foreach($products as $product)
        {
            $totalPrice += $product->price;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        // 'images'  => [$product->image]
                    ],
                    'unit_amount' => 2000,
                ],
                'quantity' => 1,
            ];
        }
        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success',[],true)."?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel',[],true),
        ]);

        $order = new Order();
        $order->status = 'unpaid';
        $order->total_price = $totalPrice;
        $order->session_id = $checkout_session->id;
        $order->save();

        return redirect($checkout_session->url);
    }

    public  function success(Request $request)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));
        
        $checkout_session_id = $request->get('session_id');

        // dd($checkout_session_id);

        try
        {
            $session = $stripe->checkout->sessions->retrieve($checkout_session_id);
            // dd($session);
            $customer = $stripe->customers->retrieve($session->customer_details);
            // $customerName = $customer['name'];
            echo "<h1>Thanks for your order, $customer->name!</h1>";
            http_response_code(200);
        }
        catch(Error $e)
        {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

        return view('product.checkout-success',compact('customer'));
    }

    public function cancel()
    {

    }
}
