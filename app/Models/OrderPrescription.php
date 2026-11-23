<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPrescription extends Model
{
    protected $fillable = [
        'order_id',
        'right_sph',
        'right_cyl',
        'right_axis',
        'right_va',
        'right_pd',
        'left_sph',
        'left_cyl',
        'left_axis',
        'left_va',
        'left_pd',
        'add_right',
        'add_left',
        'ipd',
        'frame_details',
        'lens_details',
        'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
