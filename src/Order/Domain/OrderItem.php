<?php

declare(strict_types=1);

namespace Order\Domain;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Domain\Concerns\HasUuid;

#[Fillable(['order_id', 'product_id', 'quantity', 'unit_price_cents', 'subtotal_cents'])]
class OrderItem extends Model
{
    use HasFactory;

    use HasUuid;

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Catalog\Domain\Product::class, 'product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
