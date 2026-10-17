<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use App\Services\MatchingService;

class ReconciliationController extends Controller
{
    public function run(MatchingService $matcher)
    {
        $outcome = $matcher->reconcile();

        return response()->json([
            'message'            => 'Reconciliation completed',
            'results'            => $outcome['results'],
            'unmatched_payments' => $outcome['unmatched_payments'],
        ]);
    }

    public function index()
    {
        return Reconciliation::with(['invoice', 'transaction'])->orderBy('id')->get();
    }
}