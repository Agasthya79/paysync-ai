<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Reconciliation;
use Illuminate\Support\Facades\DB;

class MatchingService
{
    public function reconcile(): array
    {
        return DB::transaction(function () {
            $results = [];

            // Prevent one transaction from matching multiple invoices.
            $usedTransactionIds = [];

            foreach (Invoice::query()->orderBy('id')->get() as $invoice) {
                $candidates = Transaction::query()
                    ->where('status', 'completed')
                    ->where('currency', $invoice->currency)
                    ->where('reference_id', $invoice->reference_id)
                    ->whereNotNull('reference_id')
                    ->whereNotIn('id', $usedTransactionIds)
                    ->get();

                if (!$invoice->reference_id || $candidates->isEmpty()) {
                    $result = [
                        'transaction_id' => null,
                        'status' => 'missing_payment',
                        'confidence' => 0,
                        'explanation' =>
                            'No unused completed transaction with the same reference and currency was found.',
                    ];
                } elseif ($candidates->count() > 1) {
                    $result = [
                        'transaction_id' => null,
                        'status' => 'duplicate_candidates',
                        'confidence' => 0,
                        'explanation' =>
                            'Multiple eligible transactions share this reference. Manual review is required.',
                    ];
                } else {
                    $transaction = $candidates->first();

                    $amountMatches =
                        (int) $invoice->amount_cents ===
                        (int) $transaction->amount_cents;

                    $result = [
                        'transaction_id' => $transaction->id,
                        'status' => $amountMatches
                            ? 'matched'
                            : 'amount_mismatch',
                        'confidence' => $amountMatches ? 100 : 70,
                        'explanation' => $amountMatches
                            ? 'Reference, currency, and amount match.'
                            : 'Reference and currency match, but the amounts differ. Review for a partial payment or data error.',
                    ];

                    $usedTransactionIds[] = $transaction->id;
                }

                Reconciliation::updateOrCreate(
                    ['invoice_id' => $invoice->id],
                    $result
                );

                $results[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    ...$result,
                ];
            }

            return $results;
        });
    }
}