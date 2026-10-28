<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use App\Models\ReconciliationRun;
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
}