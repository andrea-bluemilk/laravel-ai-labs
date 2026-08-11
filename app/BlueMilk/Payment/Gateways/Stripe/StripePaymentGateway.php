<?php

namespace App\BlueMilk\Payment\Gateways\Stripe;

use App\BlueMilk\Payment\Exceptions\PaymentException;
use App\BlueMilk\Payment\PayableOrder;
use App\BlueMilk\Payment\PaymentFactory;
use App\BlueMilk\Payment\PaymentGateway;

class StripePaymentGateway implements PaymentGateway
{
    protected $order;

    /**
     * Set the payable order.
     *
     *
     * @return PaymentGateway
     */
    public function setOrder(PayableOrder $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Start the payment procedure
     *
     * @return string
     *
     * @throws PaymentException
     */
    public function initPayment()
    {
        return route('stripe.payment', ['id' => $this->order->id]);
    }

    public function verifyPayment($request)
    {
        $mode = config('payment.gateways.stripe.mode');
        $code = config('payment.gateways.stripe.options.'.$mode.'.private_key');
        $stripe = new \Stripe\StripeClient($code);

        if ($request->payment_intent) {
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_intent);

            return ['status' => ($paymentIntent->status == 'succeeded' ? 'payed' : 'failed'), 'auth' => $paymentIntent->id, 'message' => $paymentIntent->last_payment_error];
        } else {
            $factory = new PaymentFactory;
            $gateway = $factory->gateway('scalapay');
            $gateway->setOrder($this->order);

            return $gateway->verifyPayment($request);
        }
    }

    public function simplePayment()
    {
        return route('stripe.payment', ['id' => $this->order->id]);
    }

    public function charge(float $total, string $token, string $description)
    {
        $mode = config('payment.gateways.stripe.mode');
        $code = config('payment.gateways.stripe.options.'.$mode.'.private_key');
        $stripe = new \Stripe\StripeClient($code);

        return $stripe->charges->create([
            'amount' => round($total * 100), // Stripe expects amount in cents
            'currency' => 'eur',
            'source' => $token,
            'description' => $description,
        ]);
    }

}
