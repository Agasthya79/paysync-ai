<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use App\Models\ReconciliationRun;
use App\Services\MatchingService;
use Illuminate\Http\Request;

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
}