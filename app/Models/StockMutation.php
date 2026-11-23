<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMutation extends Model
{
    protected $fillable = [
        'product_id',
        'mutation_date',
        'mutation_type', // IN_PURCHASE, OUT_SALE, ADJUSTMENT_OPNAME, DAMAGE_LOSS, RETURN
        'reference_type',
        'reference_id',
        'qty_in',
        'qty_out',
        'balance_stock',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'mutation_date' => 'datetime',
            'qty_in' => 'integer',
            'qty_out' => 'integer',
            'balance_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
