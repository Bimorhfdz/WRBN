<?php

namespace App\Services\Payment;

class PaymentGatewayFactory
{
    public static function make(): PaymentGateway
    {
        if (config('wrbn.payment_provider') === 'dummy') {
            return new DummyPaymentGateway;
        }

        return new ExternalPaymentGateway(
            (string) config('wrbn.payment_api_url'),
            (string) config('wrbn.payment_api_key'),
        );
    }
}
