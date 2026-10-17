<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class CsvImportService
{
    private const REQUIRED_HEADERS = [
        'external_id', 'reference_id', 'amount', 'currency', 'status', 'paid_at',
    ];

    /**
     * Convert "1,500.50" to 150050 using string math only (never floats).
     * Returns null for anything that isn't a positive amount with at most 2 decimals.
     */
    public static function toCents(string $raw): ?int
    {
        $s = trim($raw);

        // Either plain digits, or digits with correct thousands commas, then optional .1 or .12
        if (!preg_match('/^(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?$/', $s)) {
            return null;
        }

        $parts = explode('.', str_replace(',', '', $s));
        $cents = ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '0', 2, '0');

        return $cents > 0 ? $cents : null;
    }

    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException('Could not open the uploaded file.');
        }

        try {
            $headerRow = fgetcsv($handle);
            if ($headerRow === false || $headerRow === [null]) {
                throw new InvalidArgumentException('The file is empty.');
            }

            // Lowercase, trim, and strip the invisible BOM that Excel adds to CSV files
            $headers = array_map(
                fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))),
                $headerRow
            );

            $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
            if ($missing) {
                throw new InvalidArgumentException('Missing required column(s): ' . implode(', ', $missing));
            }

            $imported = 0;
            $errors = [];
            $seen = []; // external_id => line where it was imported
            $line = 1;  // header is line 1, so the first data row is line 2 (matches Excel)

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($row === [null]) {
                    continue; // blank line
                }

                if (count($row) !== count($headers)) {
                    $errors[] = $this->error($line, null, 'Wrong number of columns (expected ' . count($headers) . ', got ' . count($row) . ').');
                    continue;
                }

                $data = array_combine($headers, array_map('trim', $row));

                // Normalize: empty strings become null, currency uppercase, status lowercase
                $data['reference_id'] = $data['reference_id'] === '' ? null : $data['reference_id'];
                $data['paid_at'] = $data['paid_at'] === '' ? null : $data['paid_at'];
                $data['currency'] = strtoupper($data['currency']);
                $data['status'] = $data['status'] === '' ? 'completed' : strtolower($data['status']);
                $externalId = $data['external_id'] !== '' ? $data['external_id'] : null;

                $cents = self::toCents($data['amount']);
                if ($cents === null) {
                    $errors[] = $this->error($line, $externalId, 'Invalid amount "' . $data['amount'] . '". Use a positive number with at most 2 decimals.');
                    continue;
                }

                $validator = Validator::make($data, [
                    'external_id'  => ['required', 'string', 'max:100'],
                    'reference_id' => ['nullable', 'string', 'max:100'],
                    'currency'     => ['required', Rule::in(['USD', 'EUR', 'GBP', 'INR'])],
                    'status'       => ['required', Rule::in(['completed', 'pending', 'failed', 'refunded'])],
                    'paid_at'      => ['nullable', 'date_format:Y-m-d'],
                ]);

                if ($validator->fails()) {
                    $errors[] = $this->error($line, $externalId, implode(' ', $validator->errors()->all()));
                    continue;
                }

                if (isset($seen[$externalId])) {
                    $errors[] = $this->error($line, $externalId, 'Duplicate of line ' . $seen[$externalId] . ' in this file.');
                    continue;
                }

                if (Transaction::where('external_id', $externalId)->exists()) {
                    $errors[] = $this->error($line, $externalId, 'A transaction with this external_id already exists.');
                    continue;
                }

                Transaction::create([
                    'external_id'  => $externalId,
                    'reference_id' => $data['reference_id'],
                    'amount_cents' => $cents,
                    'currency'     => $data['currency'],
                    'source'       => 'csv',
                    'status'       => $data['status'],
                    'paid_at'      => $data['paid_at'],
                ]);

                $seen[$externalId] = $line;
                $imported++;
            }

            return [
                'imported' => $imported,
                'skipped'  => count($errors),
                'errors'   => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function error(int $line, ?string $externalId, string $reason): array
    {
        return ['line' => $line, 'external_id' => $externalId, 'reason' => $reason];
    }
}