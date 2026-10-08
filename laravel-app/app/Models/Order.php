<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'customer_id', 'ordered_at', 'status', 'origin', 'destination', 'remarks', 'total', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('line_no');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
