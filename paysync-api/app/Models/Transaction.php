<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'external_id',
        'reference_id',
        'amount_cents',
        'currency',
        
        'source',
        'status',
        'paid_at',
    ];

    // Include the display amount whenever the model is turned into JSON
    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at'=>'datetime',
        ];
    }

    // Display only: 150000 becomes "1500.00". Never compare on this value.
    protected function amount(): Attribute
    {
        return Attribute::get(
            fn () => sprintf('%d.%02d', intdiv($this->amount_cents, 100), $this->amount_cents % 100)
        );
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }
}