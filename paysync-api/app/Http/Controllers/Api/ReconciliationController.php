<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reconciliation;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;

class ReconciliationController extends Controller
{
    public function run(MatchingService $matcher): JsonResponse
    {
        return response()->json([
            'message' => 'Reconciliation completed',
            'results' => $matcher->reconcile(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Reconciliation::with([
                'invoice',
                'transaction',
            ])->latest()->get(),
        ]);
    }
}