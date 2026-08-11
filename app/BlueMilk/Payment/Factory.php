<?php

namespace App\BlueMilk\Payment;

use App\BlueMilk\Payment\Gateways\Stripe\StripePaymentGateway;
use App\BlueMilk\Payment\Gateways\Unicredit\UnicreditPaymentGateway;

class Factory
{
    /**
     * @return \App\BlueMilk\Payment\PaymentGateway
     */
    public static function make(string $driver, array $options)
    {
        switch ($driver) {
            case 'stripe':
                return new StripePaymentGateway;
            case 'unicredit':
                return new UnicreditPaymentGateway;

            default:
                throw new \InvalidArgumentException(sprintf('No driver found for gateway "%s".', $driver));
        }
    }
}
