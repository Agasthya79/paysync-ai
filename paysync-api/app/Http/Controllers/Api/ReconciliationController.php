<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use App\Models\ReconciliationRun;
use App\Services\GeminiService;
use App\Services\MatchingService;

class ReconciliationController extends Controller
{
    public function run(MatchingService $matcher)
    {
        $outcome = $matcher->reconcile();

        return response()->json([
            'message'            => 'Reconciliation completed',
            'run_id'             => $outcome['run_id'],
            'results'            => $outcome['results'],
            'unmatched_payments' => $outcome['unmatched_payments'],
        ]);
    }

    // Current state = the rows from the latest run
    public function index()
    {
        $run = ReconciliationRun::latest('id')->first();

        if (!$run) {
            return [];
        }

        return Reconciliation::with(['invoice', 'transaction', 'transactions'])
            ->where('run_id', $run->id)
            ->orderBy('invoice_id')
            ->get();
    }

    // History of runs, newest first
    public function runs()
    {
        return ReconciliationRun::latest('id')->paginate(15);
    }

    // A human confirms a clean match. Only matched / split_matched can be approved.
    public function approve(Reconciliation $reconciliation)
    {
        if (!in_array($reconciliation->status, ['matched', 'split_matched'], true)) {
            return response()->json([
                'message' => 'Only matched or split_matched results can be approved. This one is "' . $reconciliation->status . '".',
            ], 422);
        }

        // Only the latest run's results are current; older rows are history.
        $latestRunId = ReconciliationRun::max('id');
        if ($reconciliation->run_id !== $latestRunId) {
            return response()->json([
                'message' => 'This result is from an older run. Run reconciliation again and approve the new result.',
            ], 422);
        }

        $reconciliation->invoice->update(['status' => 'paid']);

        return response()->json([
            'message'        => 'Invoice marked as paid.',
            'invoice_id'     => $reconciliation->invoice_id,
            'invoice_status' => 'paid',
        ]);
    }

    // AI explains the engine's decision. It only receives validated facts and never changes the result.
    public function explain(Reconciliation $reconciliation, GeminiService $gemini)
    {
        if ($reconciliation->ai_explanation) {
            return response()->json([
                'explanation' => $reconciliation->ai_explanation,
                'source'      => 'ai',
                'cached'      => true,
            ]);
        }

        $invoice = $reconciliation->invoice;

        $text = $gemini->explain([
            'invoice_number'     => $invoice->invoice_number,
            'currency'           => $invoice->currency,
            'status'             => $reconciliation->status,
            'engine_explanation' => $reconciliation->explanation,
            'evidence'           => $reconciliation->evidence,
            'recommended_action' => $reconciliation->next_action,
        ]);

        if ($text === null) {
            return response()->json([
                'explanation' => $reconciliation->explanation,
                'source'      => 'template',
                'cached'      => false,
            ]);
        }

        $reconciliation->update(['ai_explanation' => $text]);

        return response()->json([
            'explanation' => $text,
            'source'      => 'ai',
            'cached'      => false,
        ]);
    }
}