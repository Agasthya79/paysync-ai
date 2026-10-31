<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class PayPalService
{
    public function isMock(): bool
    {
        return config('services.paypal.mode', 'mock') === 'mock';
    }

    public function createOrder(int $amountCents, string $currency, string $description): array
    {
        if ($this->isMock()) {
            return ['order_id' => 'MOCK-' . strtoupper(Str::random(14)), 'approve_url' => null];
        }

        throw new RuntimeException('Real PayPal mode is not implemented yet.');
    }

    public function capture(string $orderId, int $expectedCents, string $expectedCurrency): array
    {
        if ($this->isMock()) {
            // Simulates PayPal's COMPLETED capture response.
            return [
                'status'       => 'COMPLETED',
                'capture_id'   => 'MOCKCAP-' . strtoupper(Str::random(12)),
                'amount_cents' => $expectedCents,
                'currency'     => $expectedCurrency,
            ];
        }

        throw new RuntimeException('Real PayPal mode is not implemented yet.');
    }
}