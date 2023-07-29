<?php

namespace App\Http\Controllers;

use Error;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            $customer = $session->customer_details;
            // $customerName = $customer['name'];
            echo "<h1>Thanks for your order, $customer->name!</h1>";
            http_response_code(200);

            $order = Order::where('session_id',$session->id)->first();
            if(!$order)
            {
                throw new NotFoundHttpException();
            }

            if($order && $order->status === 'unpaid')
                {
                    $order->status = 'paid';
                    $order->save();
                    //  send email
                }

            return view('product.checkout-success',compact('customer'));
        }
        catch(Error $e)
        {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }

    }

    public function cancel()
    {

    }

    public function webhook()
    {
        // The library needs to be configured with your account's secret key.
        // Ensure the key is kept out of any version control system you might be using.
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

        // This is your Stripe CLI webhook secret for testing your endpoint locally.
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try
        {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        }
        catch(\UnexpectedValueException $e)
        {
            // Invalid payload
            // http_response_code(400);
            // exit();
            return response($e,400);
        }
        catch(\Stripe\Exception\SignatureVerificationException $e)
        {
            // Invalid signature
            // http_response_code(400);
            // exit();
            return response($e,400);
        }

        // Handle the event
        switch ($event->type)
        {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $sessionId = $session->id;

                $order = Order::where('session_id',$session->id)->first();
                if($order && $order->status === 'unpaid')
                {
                    $order->status = 'paid';
                    $order->save();
                    //  send email
                }
            // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        // http_response_code(200);
        return response('',200);
    }
}
