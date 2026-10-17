<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $ref, int $cents, string $number): Invoice
    {
        return Invoice::create([
            'invoice_number' => $number, 'vendor_name' => 'T',
            'reference_id' => $ref, 'amount_cents' => $cents, 'currency' => 'USD',
        ]);
    }

    private function payment(string $ref, int $cents, string $externalId): Transaction
    {
        return Transaction::create([
            'external_id' => $externalId, 'reference_id' => $ref,
            'amount_cents' => $cents, 'currency' => 'USD',
            'source' => 'csv', 'status' => 'completed',
        ]);
    }

    public function test_underpaid_invoice_is_flagged(): void
    {
        $this->invoice('REF-1', 100000, 'INV-1');
        $this->payment('REF-1', 90000, 'TXN-1');

        $out = app(MatchingService::class)->reconcile();

        $this->assertSame('underpaid', $out['results'][0]['status']);
    }
}