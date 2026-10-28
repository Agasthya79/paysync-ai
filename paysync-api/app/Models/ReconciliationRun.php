<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'invoices_checked',
        'matched',
        'needs_review',
        'unmatched_payments',
    ];

    protected function casts(): array
    {
        return [
            'invoices_checked'   => 'integer',
            'matched'            => 'integer',
            'needs_review'       => 'integer',
            'unmatched_payments' => 'array',
        ];
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class, 'run_id');
    }
}