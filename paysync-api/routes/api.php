<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TransactionController;

Route::get('/health', function () {
    return response()->json([
        'app' => 'PaySync AI',
        'status' => 'running',
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/reconciliation/run', [
    ReconciliationController::class,
    'run',
]);

Route::get('/reconciliation', [
    ReconciliationController::class,
    'index',
]);

Route::apiResource('invoices', InvoiceController::class)->except(['destroy']);
Route::apiResource('transactions', TransactionController::class)->except(['destroy']);