<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'invoice_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'user_id',
        'order_date',
        'delivery_date',
        'subtotal',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'net_total',
        'advance_payment',
        'payment_method',
        'balance_due',
        'payment_status',
        'order_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'delivery_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_total' => 'decimal:2',
            'advance_payment' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(OrderPrescription::class);
    }
}
