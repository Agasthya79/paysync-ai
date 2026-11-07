<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaypalController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransactionImportController;

Route::get('/health', function () {
    return response()->json([
        'app' => 'PaySync AI',
        'status' => 'running',
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/reconciliation/run', [ReconciliationController::class, 'run']);
Route::get('/reconciliation', [ReconciliationController::class, 'index']);
Route::get('/reconciliation/runs', [ReconciliationController::class, 'runs']);
Route::post('/reconciliation/{reconciliation}/approve', [ReconciliationController::class, 'approve']);
Route::post('/reconciliation/{reconciliation}/explain', [ReconciliationController::class, 'explain']);

Route::apiResource('invoices', InvoiceController::class)->except(['destroy']);

Route::post('transactions/import', TransactionImportController::class);
Route::apiResource('transactions', TransactionController::class)->except(['destroy']);

Route::post('invoices/{invoice}/paypal/order', [PaypalController::class, 'createOrder']);
Route::post('paypal/orders/{orderId}/capture', [PaypalController::class, 'capture']);
Route::get('paypal/return', [PaypalController::class, 'paypalReturn']);
Route::get('paypal/cancel', [PaypalController::class, 'paypalCancel']);