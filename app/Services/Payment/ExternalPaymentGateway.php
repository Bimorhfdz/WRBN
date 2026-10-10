<?php

namespace App\Services\Payment;

class ExternalPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
    ) {}

    public function createPayment(array $payment): array
    {
        throw new \RuntimeException('External payment provider belum diimplementasikan. Hubungkan adapter gateway di payment-service.');
    }

    public function pay(array $payment, ?string $method): array
    {
        throw new \RuntimeException('External payment provider belum diimplementasikan. Gunakan webhook gateway pada production.');
    }
}
