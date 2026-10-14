<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::latest('id')->paginate(15);
    }

    public function store(InvoiceRequest $request)
    {
        $invoice = Invoice::create($request->validated());

        return response()->json($invoice, 201);
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load('reconciliations');
    }

    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        $invoice->update($request->validated());

        return $invoice;
    }
}