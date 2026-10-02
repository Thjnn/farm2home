<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Basket;
use App\Models\BasketItem;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Support\ShippingCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderService
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    /**
     * Create an order with its lines, price and shipping fee in one transaction.
     *
     * @param  array{name: string, phone: string, address?: ?string, delivery_zone_id: int|string, delivery_date: string, time_slot: string, order_type: string, basket_id?: int|string|null, items?: ?array<int, array{product_id: int|string, quantity: int|string}>, payment_method: string, data_consent?: mixed, note?: ?string, subscription_id?: ?int}  $data
     *
     * @throws ValidationException when there is no valid order line
     */
    public function place(array $data, OrderChannel $channel): Order
    {
        return DB::transaction(function () use ($data, $channel) {
            $zone = DeliveryZone::findOrFail($data['delivery_zone_id']);
            $type = OrderType::from($data['order_type']);

            [$lines, $subtotal] = $type === OrderType::Basket
                ? $this->basketLines(Basket::with('items.product')->where('is_active', true)->findOrFail($data['basket_id']))
                : $this->singleLines($data['items'] ?? []);

            $customer = $this->upsertCustomer($data, $zone, $channel);
            $shipping = ShippingCalculator::forZone($zone, $subtotal);
            $date = CarbonImmutable::parse($data['delivery_date'])->startOfDay();

            $order = $this->createWithCode($date, [
                'customer_id' => $customer->id,
                'subscription_id' => $data['subscription_id'] ?? null,
                'delivery_zone_id' => $zone->id,
                'order_type' => $type,
                'channel' => $channel,
                'delivery_date' => $date->toDateString(),
                'time_slot' => $data['time_slot'],
                'delivery_method' => $zone->isPickup() ? DeliveryMethod::Pickup : DeliveryMethod::Home,
                'delivery_address' => $zone->isPickup() ? null : ($data['address'] ?? null),
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping,
                'discount' => 0,
                'total' => $subtotal + $shipping,
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'payment_status' => PaymentStatus::Unpaid,
                'status' => OrderStatus::Pending,
                'note' => $data['note'] ?? null,
            ]);

            $order->items()->createMany($lines);

            return $order->load('items.product', 'customer', 'zone');
        });
    }

    /**
     * @throws DomainException when the transition is not allowed or a cancel reason is missing
     */
    public function transition(Order $order, OrderStatus $to, ?string $cancelReason = null): Order
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new DomainException("Không thể chuyển đơn từ \"{$order->status->label()}\" sang \"{$to->label()}\".");
        }

        if ($to === OrderStatus::Cancelled && blank($cancelReason)) {
            throw new DomainException('Cần nhập lý do hủy đơn.');
        }

        return DB::transaction(function () use ($order, $to, $cancelReason) {
            $order->status = $to;

            if ($to === OrderStatus::Confirmed) {
                $order->confirmed_at = now();
            }

            if ($to === OrderStatus::Delivered) {
                $this->markDelivered($order);
            }

            if ($to === OrderStatus::Cancelled) {
                $order->cancel_reason = $cancelReason;
                // Trả tồn về lô (quyết định D13).
                $order->items->each(fn ($item) => $item->batches()->detach());
            }

            $order->save();

            if ($to === OrderStatus::Delivered) {
                $this->loyalty->awardForDeliveredOrder($order);
            }

            return $order;
        });
    }

    private function markDelivered(Order $order): void
    {
        $order->delivered_at = now();

        if ($order->payment_method === PaymentMethod::Cod) {
            $order->payment_status = PaymentStatus::Paid;
        }

        foreach ($order->items()->with('batches')->get() as $item) {
            if ($item->fulfilled_quantity !== null) {
                continue;
            }

            $allocated = (int) $item->batches->sum('pivot.quantity');
            $item->update(['fulfilled_quantity' => $allocated > 0 ? $allocated : $item->quantity]);
        }
    }

    /**
     * Khách định danh bằng số điện thoại: đặt lại thì cập nhật, không tạo khách trùng.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertCustomer(array $data, DeliveryZone $zone, OrderChannel $channel): Customer
    {
        $customer = Customer::firstOrNew(['phone' => $data['phone']]);

        if (! $customer->exists) {
            $customer->source = $channel->value;
        }

        $customer->name = $data['name'];
        $customer->delivery_zone_id = $zone->id;

        if (filled($data['address'] ?? null)) {
            $customer->address = $data['address'];
        }

        if (! empty($data['data_consent'])) {
            $customer->data_consent = true;
        }

        $customer->save();

        return $customer;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function basketLines(Basket $basket): array
    {
        $lines = $basket->items->map(fn (BasketItem $item) => [
            'product_id' => $item->product_id,
            'basket_id' => $basket->id,
            'quantity' => $item->quantity,
            'weight_kg' => round($item->quantity * (float) $item->product->pack_weight_kg, 3),
            'unit_price' => 0,
            'line_total' => 0,
        ])->all();

        return [$lines, (int) $basket->price];
    }

    /**
     * @param  array<int, array{product_id: int|string, quantity: int|string}>  $items
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function singleLines(array $items): array
    {
        $quantities = collect($items)
            ->groupBy('product_id')
            ->map(fn ($group) => (int) $group->sum('quantity'))
            ->filter(fn (int $quantity) => $quantity > 0);

        $products = Product::where('is_active', true)->findMany($quantities->keys());

        if ($quantities->isEmpty() || $products->count() !== $quantities->count()) {
            throw ValidationException::withMessages(['items' => 'Vui lòng chọn ít nhất một loại rau còn bán.']);
        }

        $lines = [];
        $subtotal = 0;

        foreach ($products as $product) {
            $quantity = $quantities[$product->id];
            $lineTotal = $quantity * (int) $product->price;
            $lines[] = [
                'product_id' => $product->id,
                'basket_id' => null,
                'quantity' => $quantity,
                'weight_kg' => round($quantity * (float) $product->pack_weight_kg, 3),
                'unit_price' => (int) $product->price,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineTotal;
        }

        return [$lines, $subtotal];
    }

    /**
     * Mã đơn DH{yymmdd}-{seq} theo ngày giao; thử lại khi một đơn khác vừa lấy cùng số.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createWithCode(CarbonInterface $date, array $attributes): Order
    {
        $prefix = 'DH'.$date->format('ymd').'-';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $last = Order::where('code', 'like', $prefix.'%')->lockForUpdate()->max('code');
            $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

            try {
                return Order::create($attributes + ['code' => $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('Không tạo được mã đơn, vui lòng thử lại.');
    }
}
