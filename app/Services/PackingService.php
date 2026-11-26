<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đóng gói: 1 túi = 1 rau × 1 lô trong 1 đơn (dòng giỏ và dòng lẻ cùng lô gộp chung túi).
 * Gói chưa gán lô hiện thành túi "thiếu", không đóng được.
 */
class PackingService
{
    public function __construct(private OrderService $orders) {}

    /**
     * Đơn đã xác nhận / đang đóng gói của ngày giao, xếp theo khu vực, khung giờ.
     *
     * @return Collection<int, Order>
     */
    public function orders(CarbonInterface $date): Collection
    {
        return Order::with(['customer', 'zone', 'items.product', 'items.batches.farmer'])
            ->whereDate('delivery_date', $date->toDateString())
            ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Packing])
            ->get()
            ->sortBy(fn (Order $order) => [$order->zone->name, $order->time_slot, $order->id])
            ->values();
    }

    /**
     * Tổng soạn theo rau cho cả ngày.
     *
     * @return Collection<int, array{product: Product, packs: int, kg: float, allocated: int, short: int}>
     */
    public function pickList(CarbonInterface $date): Collection
    {
        return $this->orders($date)
            ->flatMap(fn (Order $order) => $order->items)
            ->groupBy('product_id')
            ->map(function (Collection $items) {
                $product = $items->first()->product;
                $packs = (int) $items->sum('quantity');
                $allocated = (int) $items->sum(fn (OrderItem $item) => $item->batches->sum('pivot.quantity'));

                return [
                    'product' => $product,
                    'packs' => $packs,
                    'kg' => round($packs * (float) $product->pack_weight_kg, 3),
                    'allocated' => $allocated,
                    'short' => max(0, $packs - $allocated),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Các túi của một đơn: túi thường theo rau rồi theo lô, túi thiếu cuối cùng.
     *
     * @return Collection<int, array{key: string, product: Product, batch: ?Batch, packs: int, kg: float, packed: bool, short: bool}>
     */
    public function bagsFor(Order $order): Collection
    {
        $order->loadMissing('items.product', 'items.batches.farmer');

        $bags = $order->items
            ->flatMap(fn (OrderItem $item) => $item->batches->map(fn ($batch) => ['item' => $item, 'batch' => $batch]))
            ->groupBy(fn (array $row) => $row['batch']->id)
            ->map(function (Collection $rows) {
                $product = $rows->first()['item']->product;
                $packs = (int) $rows->sum(fn (array $row) => $row['batch']->pivot->quantity);

                return [
                    'key' => (string) $rows->first()['batch']->id,
                    'product' => $product,
                    'batch' => $rows->first()['batch'],
                    'packs' => $packs,
                    'kg' => round($packs * (float) $product->pack_weight_kg, 3),
                    'packed' => $rows->every(fn (array $row) => $row['batch']->pivot->packed_at !== null),
                    'short' => false,
                ];
            })
            ->sortBy(fn (array $bag) => [$bag['product']->id, $bag['batch']->id])
            ->values();

        $shortBags = $order->items
            ->groupBy('product_id')
            ->map(function (Collection $items) {
                $product = $items->first()->product;
                $packs = (int) $items->sum(fn (OrderItem $item) => $item->quantity - $item->batches->sum('pivot.quantity'));

                return [
                    'key' => 'short-'.$product->id,
                    'product' => $product,
                    'batch' => null,
                    'packs' => $packs,
                    'kg' => round($packs * (float) $product->pack_weight_kg, 3),
                    'packed' => false,
                    'short' => true,
                ];
            })
            ->filter(fn (array $bag) => $bag['packs'] > 0)
            ->sortKeys()
            ->values();

        return $bags->concat($shortBags);
    }

    public function isFullyPacked(Order $order): bool
    {
        $bags = $this->bagsFor($order);

        return $bags->isNotEmpty() && $bags->every(fn (array $bag) => $bag['packed']);
    }

    /**
     * Tick / bỏ tick một túi (mọi dòng pivot của đơn với lô đó). Túi đầu tiên của đơn đã xác nhận đưa đơn sang Đang đóng gói.
     *
     * @throws ModelNotFoundException lô không nằm trong đơn
     * @throws DomainException đơn không còn ở bước đóng gói, hoặc kế hoạch chưa chốt
     */
    public function markBag(Order $order, Batch $batch, bool $packed): void
    {
        DB::transaction(function () use ($order, $batch, $packed) {
            $rows = $this->pivotRows($order)->where('batch_id', $batch->id);

            if (! $rows->exists()) {
                throw (new ModelNotFoundException)->setModel(Batch::class, [$batch->id]);
            }

            $this->ensurePackable($order, $packed);
            $rows->update(['packed_at' => $packed ? now() : null]);
        });
    }

    /**
     * Tick hết các túi đã có lô của đơn.
     */
    public function markOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->ensurePackable($order, true);
            $this->pivotRows($order)->whereNull('packed_at')->update(['packed_at' => now()]);
        });
    }

    private function ensurePackable(Order $order, bool $packing): void
    {
        if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Packing], true)) {
            throw new DomainException('Đơn không còn ở bước đóng gói.');
        }

        if ($packing && $order->status === OrderStatus::Confirmed) {
            $this->orders->transition($order, OrderStatus::Packing);
        }
    }

    private function pivotRows(Order $order): Builder
    {
        return DB::table('order_item_batches')
            ->whereIn('order_item_id', $order->items()->select('id'));
    }
}
