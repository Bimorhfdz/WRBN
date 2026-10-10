<?php

namespace App\Services\Payment;

interface PaymentGateway
{
    /**
     * @param  array<string, mixed>  $payment
     * @return array{providerReference: string, status: string}
     */
    public function createPayment(array $payment): array;

    /**
     * @param  array<string, mixed>  $payment
     * @return array{providerReference: string, status: string, method?: string}
     */
    public function pay(array $payment, ?string $method): array;
}
