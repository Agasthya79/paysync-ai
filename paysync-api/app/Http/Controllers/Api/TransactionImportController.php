<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CsvImportService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TransactionImportController extends Controller
{
    public function __invoke(Request $request, CsvImportService $importer)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'], // 2 MB
        ]);

        try {
            $summary = $importer->import($request->file('file')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($summary);
    }
}