<?php

namespace App\Services\Payment;

class DummyPaymentGateway implements PaymentGateway
{
    public function createPayment(array $payment): array
    {
        return [
            'providerReference' => 'DUMMY-'.$payment['id'],
            'status' => 'pending',
        ];
    }

    public function pay(array $payment, ?string $method): array
    {
        return [
            'providerReference' => $payment['provider_reference'] ?? ('DUMMY-'.$payment['id']),
            'status' => 'paid',
            'method' => $method ?: 'qris',
        ];
    }
}
