<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaypalOrder;
use App\Models\Transaction;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaypalController extends Controller
{
    public function createOrder(Invoice $invoice, PayPalService $paypal)
    {
        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'This invoice is already paid.'], 422);
        }
        if (!$invoice->reference_id) {
            return response()->json(['message' => 'The invoice needs a reference ID so the payment can be matched.'], 422);
        }

        try {
            // The amount always comes from the database, never from the client.
            $order = $paypal->createOrder((int) $invoice->amount_cents, $invoice->currency, $invoice->invoice_number);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $record = PaypalOrder::create([
            'order_id'     => $order['order_id'],
            'invoice_id'   => $invoice->id,
            'amount_cents' => (int) $invoice->amount_cents,
            'currency'     => $invoice->currency,
        ]);

        return response()->json([
            'order_id'    => $record->order_id,
            'approve_url' => $order['approve_url'],
            'mode'        => $paypal->isMock() ? 'mock' : 'sandbox',
            'amount'      => $invoice->amount,
            'currency'    => $invoice->currency,
        ], 201);
    }

    public function capture(string $orderId, PayPalService $paypal)
    {
        $order = PaypalOrder::where('order_id', $orderId)->firstOrFail();

        if ($order->status !== 'created') {
            return response()->json(['message' => 'This order was already processed.'], 409);
        }

        try {
            $result = $paypal->capture($order->order_id, $order->amount_cents, $order->currency);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        // Verify the response against our own records before trusting it.
        if (
            $result['status'] !== 'COMPLETED'
            || (int) $result['amount_cents'] !== $order->amount_cents
            || $result['currency'] !== $order->currency
            || $result['capture_id'] === ''
        ) {
            $order->update(['status' => 'failed_verification']);

            return response()->json(['message' => 'Payment could not be verified.'], 422);
        }

        $transaction = DB::transaction(function () use ($order, $result) {
            $invoice = $order->invoice;

            $transaction = Transaction::create([
                'external_id'  => $result['capture_id'],
                'reference_id' => $invoice->reference_id,
                'amount_cents' => $order->amount_cents,
                'currency'     => $order->currency,
                'source'       => 'paypal',
                'status'       => 'completed',
                'paid_at'      => now(),
            ]);

            $order->update(['status' => 'captured', 'transaction_id' => $transaction->id]);

            return $transaction;
        });

        return response()->json([
            'message'        => 'Payment captured and recorded.',
            'transaction_id' => $transaction->id,
            'external_id'    => $transaction->external_id,
        ]);
    }

    // PayPal redirects the buyer here after approval. The capture happens server-side.
    public function paypalReturn(Request $request, PayPalService $paypal)
    {
        $res = $this->capture((string) $request->query('token'), $paypal);
        $ok = $res->getStatusCode() === 200;
        $msg = e($res->getData(true)['message'] ?? 'Done');

        return response(
            '<html><body style="font-family:sans-serif;text-align:center;margin-top:15vh">'
            . '<h2>' . ($ok ? 'Payment received' : 'Payment not completed') . '</h2>'
            . '<p>' . $msg . '</p><p>You can close this tab and return to PaySync AI.</p></body></html>',
            $ok ? 200 : 422
        );
    }

    public function paypalCancel()
    {
        return response(
            '<html><body style="font-family:sans-serif;text-align:center;margin-top:15vh">'
            . '<h2>Payment cancelled</h2><p>No money was taken. You can close this tab.</p></body></html>'
        );
    }
}