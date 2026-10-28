<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Reconciliation;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use App\Models\ReconciliationRun;

class MatchingService
{
    /**
     * Returns ['results' => [...per invoice...], 'unmatched_payments' => [...]].
     * The engine decides; it never guesses on money. Anything ambiguous is flagged for review.
     * "confidence" is a rule-based score, NOT a statistical probability.
     */
    public function reconcile(): array
    {
        return DB::transaction(function () {
            // Settled invoices are skipped so history stays stable.
            $invoices = Invoice::where('status', '!=', 'paid')->orderBy('id')->get();
            // Only completed payments are eligible.
            $transactions = Transaction::where('status', 'completed')->orderBy('id')->get();

            $results = [];
            $unmatched = [];

            // 1. Group invoices by reference|currency. No reference means no safe match.
            $invoiceGroups = [];
            foreach ($invoices as $invoice) {
                $key = $this->groupKey($invoice->reference_id, $invoice->currency);

                if ($key === null) {
                    $results[] = $this->result(
                        $invoice, null, 'missing_payment', 0,
                        'This invoice has no reference ID, so it cannot be matched to a payment automatically.'
                    );
                    continue;
                }

                $invoiceGroups[$key][] = $invoice;
            }

            // 2. Group transactions the same way.
            $transactionGroups = [];
            foreach ($transactions as $transaction) {
                $key = $this->groupKey($transaction->reference_id, $transaction->currency);

                if ($key === null) {
                    $unmatched[] = $this->unmatched(
                        $transaction, 'no_reference',
                        'This payment has no reference ID, so it cannot be linked to an invoice automatically.'
                    );
                    continue;
                }

                $transactionGroups[$key][] = $transaction;
            }

            // 3. Payments whose reference + currency matches no open invoice.
            foreach ($transactionGroups as $key => $groupTransactions) {
                if (!isset($invoiceGroups[$key])) {
                    foreach ($groupTransactions as $transaction) {
                        $unmatched[] = $this->unmatched(
                            $transaction, 'no_open_invoice',
                            'No open invoice has this reference and currency.'
                        );
                    }
                }
            }

            // 4. Reconcile each group that has invoices.
            foreach ($invoiceGroups as $key => $groupInvoices) {
                [$groupResults, $groupUnmatched] = $this->reconcileGroup(
                    $groupInvoices,
                    $transactionGroups[$key] ?? []
                );

                array_push($results, ...$groupResults);
                array_push($unmatched, ...$groupUnmatched);
            }

            // 5. Save the current result per invoice (history comes on Day 5).
                        usort($results, fn ($a, $b) => $a['invoice_id'] <=> $b['invoice_id']);
            usort($unmatched, fn ($a, $b) => $a['transaction_id'] <=> $b['transaction_id']);

            // 5. Record this run. Old runs are kept, never overwritten.
            $matchedCount = count(array_filter(
                $results,
                fn ($r) => in_array($r['status'], ['matched', 'split_matched'], true)
            ));

            $run = ReconciliationRun::create([
                'invoices_checked'   => count($results),
                'matched'            => $matchedCount,
                'needs_review'       => count($results) - $matchedCount,
                'unmatched_payments' => $unmatched,
            ]);

            foreach ($results as $r) {
                Reconciliation::create([
                    'run_id'         => $run->id,
                    'invoice_id'     => $r['invoice_id'],
                    'transaction_id' => $r['transaction_id'],
                    'status'         => $r['status'],
                    'confidence'     => $r['confidence'],
                    'explanation'    => $r['explanation'],
                ]);
            }

            return [
                'run_id'             => $run->id,
                'results'            => $results,
                'unmatched_payments' => $unmatched,
            ];
        });
    }

    /**
     * Reconcile one reference+currency group.
     *
     * @param  Invoice[]      $invoices
     * @param  Transaction[]  $transactions
     * @return array{0: array, 1: array}  [results, unmatched payments]
     */
    private function reconcileGroup(array $invoices, array $transactions): array
    {
        $results = [];
        $unmatched = [];

        // Anything still in these arrays hasn't been decided yet (keyed by id).
        $openInvoices = [];
        foreach ($invoices as $i) {
            $openInvoices[$i->id] = $i;
        }
        $openTransactions = [];
        foreach ($transactions as $t) {
            $openTransactions[$t->id] = $t;
        }

        // ---- PASS 1: exact amounts claim payments first ----
        $invoicesByAmount = [];
        foreach ($invoices as $i) {
            $invoicesByAmount[(int) $i->amount_cents][] = $i;
        }
        $transactionsByAmount = [];
        foreach ($transactions as $t) {
            $transactionsByAmount[(int) $t->amount_cents][] = $t;
        }

        foreach ($invoicesByAmount as $amount => $sameAmountInvoices) {
            $sameAmountTransactions = $transactionsByAmount[$amount] ?? [];

            if (!$sameAmountTransactions) {
                continue; // no exact payment; pass 2 will look at near matches
            }

            if (count($sameAmountInvoices) === 1 && count($sameAmountTransactions) === 1) {
                $invoice = $sameAmountInvoices[0];
                $transaction = $sameAmountTransactions[0];

                $results[] = $this->result(
                    $invoice, $transaction->id, 'matched', 100,
                    'Reference, currency, and amount match.'
                );

                unset($openInvoices[$invoice->id], $openTransactions[$transaction->id]);
                continue;
            }

            // Ambiguous: several invoices and/or payments with identical details.
            foreach ($sameAmountInvoices as $invoice) {
                $results[] = $this->result(
                    $invoice, null, 'duplicate_candidates', 0,
                    sprintf(
                        '%d invoice(s) and %d payment(s) share this reference, currency, and amount (%s). Manual review is required to decide which belongs to which.',
                        count($sameAmountInvoices),
                        count($sameAmountTransactions),
                        $this->money($amount)
                    )
                );
                unset($openInvoices[$invoice->id]);
            }
            foreach ($sameAmountTransactions as $transaction) {
                unset($openTransactions[$transaction->id]); // reserved for review, not "unmatched"
            }
        }

        // ---- PASS 2: leftovers in this group ----
        $leftInvoices = array_values($openInvoices);
        $leftTransactions = array_values($openTransactions);

        if (!$leftInvoices) {
            // Invoices are all settled but extra payments remain with the same reference.
            foreach ($leftTransactions as $transaction) {
                $unmatched[] = $this->unmatched(
                    $transaction, 'extra_payment',
                    'The invoice(s) with this reference were already matched to another payment. This may be a duplicate or extra payment.'
                );
            }
        } elseif (!$leftTransactions) {
            foreach ($leftInvoices as $invoice) {
                $results[] = $this->result(
                    $invoice, null, 'missing_payment', 0,
                    'No completed payment with the same reference and currency was found.'
                );
            }
        } elseif (count($leftInvoices) === 1 && count($leftTransactions) === 1) {
            $invoice = $leftInvoices[0];
            $transaction = $leftTransactions[0];
            $diff = (int) $transaction->amount_cents - (int) $invoice->amount_cents;

            $results[] = $this->result(
                $invoice,
                $transaction->id,
                $diff < 0 ? 'underpaid' : 'overpaid',
                70,
                sprintf(
                    'Reference and currency match, but the payment (%s) is %s %s the invoice (%s). Review for a partial payment or a data entry error.',
                    $this->money((int) $transaction->amount_cents),
                    $this->money(abs($diff)),
                    $diff < 0 ? 'less than' : 'more than',
                    $this->money((int) $invoice->amount_cents)
                )
            );
        } else {
            // Several invoices and/or payments left: we cannot safely pair them.
            foreach ($leftInvoices as $invoice) {
                $results[] = $this->result(
                    $invoice, null, 'duplicate_candidates', 0,
                    sprintf(
                        '%d open invoice(s) and %d payment(s) share this reference and currency but their amounts do not pair up cleanly. Manual review is required.',
                        count($leftInvoices),
                        count($leftTransactions)
                    )
                );
            }
        }

        return [$results, $unmatched];
    }

    /** "REF-7000|USD", or null when there's no usable reference. */
    private function groupKey(?string $reference, string $currency): ?string
    {
        $reference = strtoupper(trim((string) $reference));

        if ($reference === '') {
            return null;
        }

        return $reference . '|' . strtoupper(trim($currency));
    }

    private function result(Invoice $invoice, ?int $transactionId, string $status, int $confidence, string $explanation): array
    {
        return [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'transaction_id' => $transactionId,
            'status'         => $status,
            'confidence'     => $confidence,
            'explanation'    => $explanation,
        ];
    }

    private function unmatched(Transaction $transaction, string $reason, string $explanation): array
    {
        return [
            'transaction_id' => $transaction->id,
            'external_id'    => $transaction->external_id,
            'reference_id'   => $transaction->reference_id,
            'amount_cents'   => (int) $transaction->amount_cents,
            'currency'       => $transaction->currency,
            'reason'         => $reason,
            'explanation'    => $explanation,
        ];
    }

    // Display only: 150000 becomes "1500.00" (integer math, no floats)
    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}