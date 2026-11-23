<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    protected $fillable = [
        'opname_number',
        'opname_date',
        'conducted_by',
        'status', // draft, approved
        'total_items_checked',
        'total_difference_items',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date:Y-m-d',
            'total_items_checked' => 'integer',
            'total_difference_items' => 'integer',
        ];
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
