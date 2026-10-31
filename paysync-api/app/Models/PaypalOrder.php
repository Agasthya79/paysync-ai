<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaypalOrder extends Model
{
    protected $fillable = [
        'order_id', 'invoice_id', 'amount_cents', 'currency', 'status', 'transaction_id',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}