<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no auth yet
    }

    // Normalize before validating, so "usd" becomes "USD"
    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->currency)]);
        }
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'invoice_number' => [$required, 'string', 'max:100',
                Rule::unique('invoices', 'invoice_number')->ignore($this->route('invoice'))],
            'vendor_name'  => [$required, 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'amount_cents' => [$required, 'integer', 'min:1'],
            'currency'     => [$required, Rule::in(['USD', 'EUR', 'GBP', 'INR'])],
            'invoice_date' => ['nullable', 'date'],
            'status'       => ['sometimes', Rule::in(['pending', 'partially_paid', 'paid', 'disputed'])],
        ];
    }
}