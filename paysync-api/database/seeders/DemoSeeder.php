<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    private function invoice(string $no, string $ref, int $cents): void
    {
        Invoice::create([
            'invoice_number' => $no, 'vendor_name' => 'Demo Supplies Ltd',
            'reference_id' => $ref, 'amount_cents' => $cents,
            'currency' => 'USD', 'invoice_date' => '2026-10-15', 'status' => 'pending',
        ]);
    }

    private function payment(string $id, ?string $ref, int $cents): void
    {
        Transaction::create([
            'external_id' => $id, 'reference_id' => $ref, 'amount_cents' => $cents,
            'currency' => 'USD', 'source' => 'csv', 'status' => 'completed',
            'paid_at' => '2026-10-20',
        ]);
    }

    public function run(): void
    {
        // 1. Clean match
        $this->invoice('INV-1001', 'REF-1001', 150000);
        $this->payment('TXN-1001', 'REF-1001', 150000);

        // 2. Underpaid (1000.00 invoice, 900.00 paid)
        $this->invoice('INV-2001', 'REF-2001', 100000);
        $this->payment('TXN-2001', 'REF-2001', 90000);

        // 3. Split payment (400.00 + 600.00 = 1000.00)
        $this->invoice('INV-3001', 'REF-3001', 100000);
        $this->payment('TXN-3001A', 'REF-3001', 40000);
        $this->payment('TXN-3001B', 'REF-3001', 60000);

        // 4. Ambiguous: two identical invoices, one payment
        $this->invoice('INV-4001', 'REF-4000', 50000);
        $this->invoice('INV-4002', 'REF-4000', 50000);
        $this->payment('TXN-4000', 'REF-4000', 50000);

        // 5. Missing payments (used live for the PayPal and CSV demos)
        $this->invoice('INV-5001', 'REF-5001', 30000);
        $this->invoice('INV-5002', 'REF-5002', 45000);

        // 6. Payments that belong to no invoice
        $this->payment('TXN-6001', 'REF-6001', 25000); // unknown reference
        $this->payment('TXN-6002', null, 12000);       // no reference at all

        // 7. Overpaid (200.00 invoice, 250.00 paid)
        $this->invoice('INV-7001', 'REF-7001', 20000);
        $this->payment('TXN-7001', 'REF-7001', 25000);
    }
}