<?php

declare(strict_types=1);

namespace Order\Domain;

use Catalog\Domain\Product;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Exceptions\InvalidOrderStateException;
use Payment\Domain\Payment;
use Shared\Domain\Concerns\HasUuid;

#[Fillable(['customer_name', 'customer_email', 'status', 'total_cents', 'currency'])]
class Order extends Model
{
    use HasFactory;

    use HasUuid;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_cents' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }


    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): ?Payment
    {
        $payment = $this->payments()->latest('created_at')->first();

        return $payment;
    }

    public function isFinalized(): bool
    {
        return $this->status?->isFinal() ?? false;
    }

    public function addItem(Product $product, int $quantity): OrderItem
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        $unitPrice = (int) $product->price_cents;
        $subtotal = $unitPrice * $quantity;

        $item = $this->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'subtotal_cents' => $subtotal,
        ]);

        $this->refreshTotal();
        $this->save();

        return $item;
    }

    public function refreshTotal(): void
    {
        $this->total_cents = (int) $this->items()->sum('subtotal_cents');
    }

    public function confirmPayment(): void
    {
        $this->transitionTo(OrderStatus::Paid);
    }

    public function failPayment(): void
    {
        $this->transitionTo(OrderStatus::Failed);
    }

    private function transitionTo(OrderStatus $target): void
    {
        if ($this->status !== OrderStatus::Pending) {
            throw new InvalidOrderStateException(
                (string) $this->id,
                (string) $this->status?->value,
                $target->value,
            );
        }

        $this->status = $target;
    }
}
