<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function index()
    {
        return Transaction::latest('id')->paginate(15);
    }

    public function store(TransactionRequest $request)
    {
        $transaction = Transaction::create($request->validated());

        return response()->json($transaction, 201);
    }

    public function show(Transaction $transaction)
    {
        return $transaction->load('reconciliations');
    }

    public function update(TransactionRequest $request, Transaction $transaction)
    {
        $transaction->update($request->validated());

        return $transaction;
    }
}