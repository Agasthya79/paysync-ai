<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no auth yet
    }

    // Normalize before validating, so "usd" becomes "USD" and "PayPal" becomes "paypal"
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->currency)]);
        }
        if ($this->has('source')) {
            $this->merge(['source' => strtolower((string) $this->source)]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'external_id'  => [$required, 'string', 'max:100',
                Rule::unique('transactions', 'external_id')->ignore($this->route('transaction'))],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'amount_cents' => [$required, 'integer', 'min:1'],
            'currency'     => [$required, Rule::in(['USD', 'EUR', 'GBP', 'INR'])],
            'source'       => [$required, Rule::in(['csv', 'paypal'])],
            'status'       => ['sometimes', Rule::in(['completed', 'pending', 'failed', 'refunded'])],
            'paid_at'      => ['nullable', 'date'],
        ];
    }
}