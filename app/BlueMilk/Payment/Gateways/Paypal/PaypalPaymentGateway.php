<?php

namespace App\BlueMilk\Payment\Gateways\Paypal;

use App\BlueMilk\Payment\Exceptions\PaymentException;
use App\BlueMilk\Payment\PayableOrder;
use App\BlueMilk\Payment\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
// All Paypal SDK class
use PayPal\Api\Amount;
use PayPal\Api\Authorization;
use PayPal\Api\Capture;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\WebhookEvent;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

//new paypal server sdk
use PaypalServerSdkLib\Logging\LoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\RequestLoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\ResponseLoggingConfigurationBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;

class PaypalPaymentGateway implements PaymentGateway
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
        if (config('payment.gateways.paypal.options.settings.mode') == 'sandbox') {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.sandbox.client_id'),
                config('payment.gateways.paypal.options.sandbox.secret'))
            );
        } else {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.live.client_id'),
                config('payment.gateways.paypal.options.live.secret'))
            );
        }

        $api_context->setConfig(config('payment.gateways.paypal.options.settings'));

        $payer = new Payer;
        $payer->setPaymentMethod('paypal');

        $items = [];
        $item = new Item;
        $item->setName($this->order->getPaymentDescription());
        $item->setCurrency($this->order->user->currency_iso);
        $item->setQuantity(1);
        $item->setPrice($this->order->totOrdine);
        $items[] = $item;
        $itemList = new ItemList;
        $itemList->setItems($items);

        $details = new Details;
        $details->setShipping($this->order->spedizione);
        $details->setHandlingFee($this->order->contrassegno);
        $details->setSubtotal($this->order->totOrdine);

        $amount = new Amount;
        $amount->setCurrency($this->order->user->currency_iso);
        $amount->setTotal($this->order->totOrdine + $this->order->contrassegno + $this->order->spedizione);
        $amount->setDetails($details);

        $transaction = new Transaction;
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList);
        $transaction->setDescription($this->order->getPaymentDescription());
        $transaction->setInvoiceNumber($this->order->getPaymentOrderId());

        $redirectUrls = new RedirectUrls;
        $redirectUrls->setReturnUrl(LaravelLocalization::getLocalizedURL(Lang::getLocale(), route('cart.notify', ['id' => $this->order->getPaymentOrderId(), 'method' => 'paypal'])));
        $redirectUrls->setCancelUrl(LaravelLocalization::getLocalizedURL(Lang::getLocale(), route('payment.not-approved', ['id' => $this->order->getPaymentOrderId()])));

        $payment = new Payment;
        $payment->setIntent(config('payment.gateways.paypal.options.payment_action'));
        $payment->setPayer($payer);
        $payment->setRedirectUrls($redirectUrls);
        $payment->setTransactions([$transaction]);
        try {
            $payment->create($api_context);
        } catch (\Exception $ex) {
            throw new PaymentException($ex->getMessage());
        }
        $link = $payment->getApprovalLink();
        $uid = $payment->id;

        $this->order->setPaymentUid($uid);

        return $link;
    }

    /**
     * Complete the payment procedure
     *
     * @return string
     *
     * @throws PaymentException
     */
    public function verifyPayment($request)
    {
        if (config('payment.gateways.paypal.options.settings.mode') == 'sandbox') {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.sandbox.client_id'),
                config('payment.gateways.paypal.options.sandbox.secret'))
            );
        } else {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.live.client_id'),
                config('payment.gateways.paypal.options.live.secret'))
            );
        }

        $api_context->setConfig(config('payment.gateways.paypal.options.settings'));

        $payment = Payment::get($this->order->getPaymentUid(), $api_context);
        $execution = new PaymentExecution;
        $execution->setPayerId($request->PayerID);

        try {
            $result = $payment->execute($execution, $api_context);
            $payment = Payment::get($this->order->getPaymentUid(), $api_context);
        } catch (\Exception $ex) {
            throw new PaymentException($ex->getMessage());
        }

        if (config('payment.gateways.paypal.options.payment_action') == 'authorize') {
            return ['status' => $result->getState(), 'auth' => $result->transactions[0]->related_resources[0]->authorization->id, 'message' => $result->getFailureReason()];
        } else {

            return ['status' => ($result->getState() == 'approved' ? 'payed' : $result->getState()), 'auth' => $result->getId(), 'message' => $result->getFailureReason()];
        }
        // return $verify->authCode;
    }

    /**
     * Cancel the entire payment authorization
     *
     * @return string
     *
     * @throws PaymentException
     */
    public function voidPayment(string $auth_id)
    {
        if (config('payment.gateways.paypal.options.settings.mode') == 'sandbox') {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.sandbox.client_id'),
                config('payment.gateways.paypal.options.sandbox.secret'))
            );
        } else {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.live.client_id'),
                config('payment.gateways.paypal.options.live.secret'))
            );
        }

        $api_context->setConfig(config('payment.gateways.paypal.options.settings'));

        try {
            $authorization = Authorization::get($auth_id, $api_context);
            $voidedAuth = $authorization->void($api_context);
        } catch (\Exception $ex) {
            throw new PaymentException($ex->getMessage());
        }

        return $voidedAuth->getId();
    }

    /**
     * Capture the entire authorization
     *
     * @return string
     *
     * @throws PaymentException
     */
    public function capturePayment(string $auth_id, $total)
    {
        if (config('payment.gateways.paypal.options.settings.mode') == 'sandbox') {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.sandbox.client_id'),
                config('payment.gateways.paypal.options.sandbox.secret'))
            );
        } else {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.live.client_id'),
                config('payment.gateways.paypal.options.live.secret'))
            );
        }

        $api_context->setConfig(config('payment.gateways.paypal.options.settings'));

        try {
            $authorization = Authorization::get($auth_id, $api_context);
            $amt = new Amount;
            $amt->setCurrency('EUR');
            $amt->setTotal($total);
            $capture = new Capture;
            $capture->setAmount($amt);
            $getCapture = $authorization->capture($capture, $api_context);
        } catch (\Exception $ex) {
            throw new PaymentException($ex->getMessage());
        }

        return $getCapture->getId();
    }

    /**
     * Event listener
     */
    public function processEvent($request)
    {
        if (config('payment.gateways.paypal.options.settings.mode') == 'sandbox') {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.sandbox.client_id'),
                config('payment.gateways.paypal.options.sandbox.secret'))
            );
        } else {
            $api_context = new ApiContext(new OAuthTokenCredential(
                config('payment.gateways.paypal.options.live.client_id'),
                config('payment.gateways.paypal.options.live.secret'))
            );
        }

        $api_context->setConfig(config('payment.gateways.paypal.options.settings'));

        // $event = WebhookEvent::validateAndGetReceivedEvent($request->getContent(), $api_context);
        $event = new WebhookEvent($request->getContent());
        $resource = $event->getResource();
        $type = $event->getEventType();

        switch ($type) {
            case 'PAYMENT.AUTHORIZATION.CREATED': // auth ottenuta
                // DB::table('ordini')->where('payment_uid',$resource->parent_payment)->update(['status'=>'approved']);
                return;

            case 'PAYMENT.AUTHORIZATION.VOIDED': // auth cancellata
                // DB::table('ordini')->where('payment_uid',$resource->parent_payment)->update(['status'=>'cancelled']);
                // $order_id = DB::table('orders')->where('payment_uid',$resource->parent_payment)->select('id')->first();
                // DB::table('order_details')->where('order_id',$order_id->id)->update(['status'=>'cancelled','available'=>0]);
                return;

            case 'PAYMENT.CAPTURE.COMPLETED': // auth ottenuta
                // DB::table('ordini')->where('payment_uid',$resource->parent_payment)->update(['status'=>'payed']);
                // $order_id = DB::table('orders')->where('payment_uid',$resource->parent_payment)->select('id')->first();
                // DB::table('order_details')->where('order_id',$order_id->id)->where('available',1)->update(['status'=>'payed']);
                // DB::table('order_details')->where('order_id',$order_id->id)->where('available',0)->update(['status'=>'cancelled']);
                return;

            case 'PAYMENT.SALE.COMPLETED': // pagamento completato
                $order_id = DB::table('ordini')->where('payment_uid', $resource->parent_payment)->select('id')->first();
                $order_state = DB::table('ordini')->where('id', $order_id)->select('esito')->first();
                if ($order_state != 'ok') {
                    DB::table('ordini')->where('id', $order_id)->update(['esito' => 'ok']);
                    // DB::table('order_details')->where('order_id',$order_id->id)->update(['status'=>'payed']);
                    event(new PaymentReceived($order_id));
                }

            case 'PAYMENT.SALE.DENIED': // pagamento rifiutato
                $order_id = DB::table('ordini')->where('payment_uid', $resource->parent_payment)->select('id')->first();
                DB::table('ordini')->where('payment_uid', $resource->parent_payment)->update(['esito' => 'ko']);
                // DB::table('order_details')->where('order_id',$order_id->id)->update(['status'=>'failed']);
        }
        // PAYMENT.AUTHORIZATION.CREATED
        // PAYMENT.AUTHORIZATION.VOIDED
        // PAYMENT.CAPTURE.COMPLETED auth ottenuta
    }

    public function simplePayment()
    {
        return null;
    }

    public function captureOrder(string $token)
    {
        $client = PaypalServerSdkClientBuilder::init()
        ->clientCredentialsAuthCredentials(
            ClientCredentialsAuthCredentialsBuilder::init(
                config('payment.gateways.paypal.options.' . config('payment.gateways.paypal.mode') . '.client_id'),
                config('payment.gateways.paypal.options.' . config('payment.gateways.paypal.mode') . '.secret')
            )
        )
        ->environment(Environment::SANDBOX)
        ->build();

        $ordersController = $client->getOrdersController();

        $apiResponse = $ordersController->captureOrder([
            'id' => $token,
        ]);

        return $apiResponse->getResult();
    }
}
