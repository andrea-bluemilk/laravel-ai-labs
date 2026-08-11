<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UcpCompleteCheckoutRequest;
use App\Http\Requests\UcpInitCheckoutRequest;
use App\BlueMilk\Payment\Gateways\Stripe\StripePaymentGateway;
use App\BlueMilk\Payment\Gateways\Paypal\PaypalPaymentGateway;
use Illuminate\Support\Facades\Cache;

class UcpCheckoutController extends Controller
{
    protected StripePaymentGateway $stripeService;
    protected PaypalPaymentGateway $paypalService;

    public function __construct(StripePaymentGateway $stripeService, PaypalPaymentGateway $paypalService)
    {
        $this->stripeService = $stripeService;
        $this->paypalService = $paypalService;
    }

    /**
     * FASE A: L'AI chiede un preventivo (Totale + Tasse + Spedizione)
     */
    public function createSession(UcpInitCheckoutRequest $request)
    {
        $lineItems = $request->input('line_items');
        $shippingAddress = $request->input('buyer.shipping_address');

        $subtotal = 0;
        $processedItems = [];

        // 1. Calcolare il totale dell'ordine basato sui line_items
        foreach ($lineItems as $item) {
            $product = \App\BlueMilk\Models\Sku::find($item['product_id']);
            if (!$product) {
                return response()->json(['error' => 'PRODUCT_NOT_FOUND', 'message' => 'Product not found: ' . $item['product_id']], 404);
            }
            if($product->stock < $item['quantity']) {
                return response()->json(['error' => 'OUT_OF_STOCK', 'message' => 'Insufficient stock for product: ' . $item['product_id']], 400);
            }

            $itemCost = $product->price * $item['quantity'];
            $subtotal += $itemCost;

            $processedItems[] = [
                'product_id' => $product->id,
                'name' => $product->product->name,
                'quantity' => $item['quantity'],
                'price' => $product->price,
                'total' => $itemCost,
            ];
        }

        // Calcolo simulato dei costi di spedizione fissi o basati sul CAP
        $shippingCost = ($shippingAddress['country_code'] === 'IT') ? 5.90 : 15.00;

        // Calcolo tasse (es. IVA 22% inclusa scorporata o aggiunta)
        $tax = round($subtotal * 0.22, 2);
        $total = $subtotal + $shippingCost;

        // 2. // Genera un ID di sessione UCP e salva i dati temporanei in Cache per 30 minuti
        $sessionId = 'ucp_sess_' . uniqid();
        Cache::put($sessionId, [
            'items' => $processedItems,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax' => $tax,
            'total' => $total,
            'status' => 'REQUIRES_PAYMENT_METHOD',
        ], now()->addMinutes(30));

        // 3. Restituire un response con l'ID della sessione e i dettagli necessari per il frontend
        return response()->json([
            'session_id' => $sessionId,
            'totals' => [
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'shipping' => number_format($shippingCost, 2, '.', ''),
                'tax' => number_format($tax, 2, '.', ''),
                'total' => number_format($total, 2, '.', ''),
            ],
            'status' => 'REQUIRES_PAYMENT_METHOD',
        ]);
    }

    /**
     * FASE B & C: L'AI invia i dati finali del cliente e il token di pagamento crittografato
     */
    public function completeSession(string $sessionId, UcpCompleteCheckoutRequest $request)
    {
        // Logica per completare la sessione di checkout

        // 1. Recuperare la sessione di checkout cachoe o database usando $sessionId
        $sessionData = Cache::get($sessionId);
        if(!$sessionData) {
            return response()->json(['error' => 'SESSION_EXPIRED', 'message' => 'Checkout session not found or expired.'], 404);
        }

        $buyer = $request->input('buyer');
        $paymentMethod = $request->input('payment_method');
        $gateway = $paymentMethod['gateway'];
        $token = $paymentMethod['token'];

        $paymentSuccess = false;
        $transactionId = null;

        try{
            // 2. Processare il pagamento usando i dati in $request->payment_method
            if($gateway === 'stripe') {
                $charge = $this->stripeService->charge(floatval($sessionData['total']), $token, "Ordine UCP Demo - " .$buyer);
                if($charge && $charge->status === 'succeeded') {
                    $paymentSuccess = true;
                    $transactionId = $charge->id;
                }
            } elseif($gateway === 'paypal') {
                $capture = $this->paypalService->captureOrder($token);
                if($capture && $capture['status'] === 'COMPLETED') {
                    $paymentSuccess = true;
                    $transactionId = $capture['id'];
                }
            }

            if(!$paymentSuccess) {
                return response()->json(['error' => 'PAYMENT_FAILED', 'message' => 'Payment processing failed.'], 400);
            }

            // 3. Creazione e salvataggio dell'ordine nel database con stato "completato" o simile
            $order = new \App\BlueMilk\Models\Order();
            $order->ucp_session_id = $sessionId;
            $order->status = \App\BlueMilk\Enums\OrderState::PAID;
            $order->email = $buyer['email'];
            $order->surname = $buyer['name'];
            $order->street = $buyer['shipping_address']['street_address'];
            $order->city = $buyer['shipping_address']['city'];
            $order->zip = $buyer['shipping_address']['postal_code'];
            $order->country_code = $buyer['shipping_address']['country_code'];
            // $order->subtotal = $sessionData['subtotal'];
            $order->shipping_cost = $sessionData['shipping_cost'];
            $order->tax = $sessionData['tax'];
            $order->total = $sessionData['total'];
            $order->payment_auth = $gateway;
            $order->payment_uid = $token;
            $order->transaction_uid = $transactionId;
            $order->save();

            foreach ($sessionData['items'] as $item) {
                new \App\BlueMilk\Models\OrderDetail([
                    'order_id' => $order->id,
                    'buyable_id' => $item['product_id'],
                    'buyable_type' => \App\BlueMilk\Models\Sku::class,
                    'qty' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => floatval($item['price']) * intval($item['quantity'])
                ]);

                // Decremento stock fisico
                \App\BlueMilk\Models\Sku::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
            }

            // Pulizia della Cache
            Cache::forget($sessionId);

             // 4. Inviare eventuali email di conferma o notifiche
             // Mail::to($buyer['email'])->send(new OrderConfirmationMail($order));

             // 5. Restituire un response con l'esito del pagamento e eventuali dettagli aggiuntivi
             return response()->json([
                "message" => "Grazie, l'ordine è stato registrato ed è in fase di elaborazione.",
                'order_id' => (string)$order->id,
                "status" => "SUCCESS",
                "fulfillment_status" => "PENDING",
            ]);

        }catch(\Exception $e) {
            return response()->json(['error' => 'INTERNAL_ERROR', 'message' => 'Error processing order: ' . $e->getMessage()], 500);
        }





    }
}
