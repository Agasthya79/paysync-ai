<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reconciliation extends Model
{
    protected $fillable = [
        'run_id',
        'invoice_id',
        'transaction_id',
        'status',
        'confidence',
        'explanation',
        'evidence',
        'next_action',
        'ai_explanation',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'evidence'   => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'run_id');
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'reconciliation_transaction')->withTimestamps();
    }
}