<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalService
{
    public function isMock(): bool
    {
        return config('services.paypal.mode', 'mock') === 'mock';
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public function accessToken(): string
    {
        return Cache::remember('paypal_access_token', 3000, function () {
            $cfg = config('services.paypal');

            if (!$cfg['client_id'] || !$cfg['client_secret']) {
                throw new RuntimeException('PayPal credentials are missing in .env');
            }

            $res = Http::asForm()
                ->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
                ->timeout(15)
                ->post($cfg['base_url'] . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if ($res->failed()) {
                throw new RuntimeException('PayPal token request failed: HTTP ' . $res->status());
            }

            return $res->json('access_token');
        });
    }

    private function client()
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(20)
            ->withHeaders(['PayPal-Request-Id' => (string) Str::uuid()]);
    }

    /** @return array{order_id: string, approve_url: ?string} */
    public function createOrder(int $amountCents, string $currency, string $invoiceNumber): array
    {
        if ($this->isMock()) {
            return ['order_id' => 'MOCK-' . strtoupper(Str::random(14)), 'approve_url' => null];
        }

        $res = $this->client()->post(config('services.paypal.base_url') . '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $invoiceNumber,
                'description'  => 'Invoice ' . $invoiceNumber,
                'amount'       => ['currency_code' => $currency, 'value' => $this->money($amountCents)],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name'  => 'PaySync AI',
                'user_action' => 'PAY_NOW',
                'return_url'  => url('/api/paypal/return'),
                'cancel_url'  => url('/api/paypal/cancel'),
            ]]],
        ]);

        if ($res->failed()) {
            throw new RuntimeException('PayPal create order failed: HTTP ' . $res->status() . ' ' . $res->body());
        }

        $link = collect($res->json('links') ?? [])
            ->first(fn ($l) => in_array($l['rel'] ?? '', ['payer-action', 'approve'], true));

        return ['order_id' => $res->json('id'), 'approve_url' => $link['href'] ?? null];
    }

    /** @return array{status: string, capture_id: string, amount_cents: int, currency: string} */
    public function capture(string $orderId, int $expectedCents, string $expectedCurrency): array
    {
        if ($this->isMock()) {
            return [
                'status'       => 'COMPLETED',
                'capture_id'   => 'MOCKCAP-' . strtoupper(Str::random(12)),
                'amount_cents' => $expectedCents,
                'currency'     => $expectedCurrency,
            ];
        }

        $res = $this->client()
            ->withBody('{}', 'application/json')
            ->post(config('services.paypal.base_url') . "/v2/checkout/orders/{$orderId}/capture");

        if ($res->failed()) {
            throw new RuntimeException('PayPal capture failed: HTTP ' . $res->status() . ' ' . $res->body());
        }

        $cap = $res->json('purchase_units.0.payments.captures.0') ?? [];
        $ok = $res->json('status') === 'COMPLETED' && ($cap['status'] ?? '') === 'COMPLETED';

        return [
            'status'       => $ok ? 'COMPLETED' : 'NOT_COMPLETED',
            'capture_id'   => $cap['id'] ?? '',
            'amount_cents' => CsvImportService::toCents((string) ($cap['amount']['value'] ?? '')) ?? 0,
            'currency'     => $cap['amount']['currency_code'] ?? '',
        ];
    }
}