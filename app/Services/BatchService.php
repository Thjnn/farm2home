<?php

namespace App\Services;

use App\Enums\HarvestPlanStatus;
use App\Enums\OrderStatus;
use App\Enums\QualityStatus;
use App\Enums\WasteReason;
use App\Models\Batch;
use App\Models\HarvestPlanItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WasteLog;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class BatchService
{
    /**
     * Khối lượng còn lại của lô: đã nhận − đã gán cho đơn − đã ghi rau dư.
     */
    public function remainingKg(Batch $batch): float
    {
        $allocatedKg = (float) DB::table('order_item_batches')
            ->join('order_items', 'order_items.id', '=', 'order_item_batches.order_item_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_item_batches.batch_id', $batch->id)
            ->sum(DB::raw('order_item_batches.quantity * products.pack_weight_kg'));

        $wasteKg = (float) $batch->wasteLogs()->sum('quantity_kg');

        return max(0.0, round((float) $batch->quantity_kg - $allocatedKg - $wasteKg, 2));
    }

    /**
     * Quyết định D4: tồn của lô đạt chất lượng, thu hoạch trước ngày giao và còn hạn dùng.
     */
    public function availableStockKg(Product $product, CarbonInterface $forDate): float
    {
        $date = CarbonImmutable::parse($forDate)->startOfDay();

        $batches = Batch::where('product_id', $product->id)
            ->where('quality_status', QualityStatus::Passed)
            ->where('harvested_at', '<', $date)
            ->whereDate('best_before', '>=', $date->toDateString())
            ->get();

        return round($batches->sum(fn (Batch $batch) => $this->remainingKg($batch)), 2);
    }

    /**
     * Nhận rau từ nông hộ và tạo lô. Mỗi nông hộ + loại rau + ngày thu hoạch chỉ có một lô: nhận lại là sửa số.
     *
     * @throws DomainException khi kế hoạch chưa chốt, dòng chưa có nông hộ, hoặc khối lượng không dương
     */
    public function receive(HarvestPlanItem $item, float $receivedKg, ?CarbonInterface $harvestedAt = null): Batch
    {
        $item->loadMissing('plan', 'product', 'farmer');

        if (! in_array($item->plan->status, [HarvestPlanStatus::Confirmed, HarvestPlanStatus::Sent], true)) {
            throw new DomainException('Chỉ nhận hàng cho kế hoạch đã chốt.');
        }

        if ($item->farmer === null) {
            throw new DomainException('Dòng kế hoạch chưa có nông hộ.');
        }

        if ($receivedKg <= 0) {
            throw new DomainException('Khối lượng nhận phải lớn hơn 0.');
        }

        return DB::transaction(function () use ($item, $receivedKg, $harvestedAt) {
            $harvestedAt = CarbonImmutable::parse(
                $harvestedAt ?? $item->plan->harvest_date->toDateString().' '.config('farm2home.batch.harvest_time')
            );

            $item->update(['received_kg' => $receivedKg]);

            return Batch::updateOrCreate(
                ['code' => sprintf('F2H-%s-%s-%s', $harvestedAt->format('ymd'), $item->product->code, $item->farmer->code)],
                [
                    'harvest_plan_item_id' => $item->id,
                    'farmer_id' => $item->farmer_id,
                    'product_id' => $item->product_id,
                    'quantity_kg' => $receivedKg,
                    'harvested_at' => $harvestedAt,
                    'received_at' => now(),
                    'storage_guide' => $item->product->storage_guide,
                    'best_before' => $harvestedAt->addDays((int) $item->product->shelf_life_days)->toDateString(),
                ],
            );
        });
    }

    public function setQuality(Batch $batch, QualityStatus $status, ?string $note = null): Batch
    {
        $batch->update(['quality_status' => $status, 'qc_note' => $note]);

        return $batch;
    }

    /**
     * Gán lô cho các dòng đơn đã xác nhận của ngày giao. Lô hết hạn sớm nhất dùng trước;
     * đơn giỏ định kỳ được phục vụ trước, rồi theo thời điểm đặt (quyết định D12). Chạy lại không gán trùng.
     *
     * @return array{allocated_packs: int, short_packs: int}
     */
    public function allocate(CarbonInterface $deliveryDate): array
    {
        $date = CarbonImmutable::parse($deliveryDate)->startOfDay();

        return DB::transaction(function () use ($date) {
            $items = OrderItem::with('order', 'product', 'batches')
                ->whereHas('order', fn ($query) => $query
                    ->whereDate('delivery_date', $date->toDateString())
                    ->whereIn('status', [OrderStatus::Confirmed, OrderStatus::Packing]))
                ->get()
                ->sortBy(fn (OrderItem $item) => [
                    $item->order->subscription_id === null ? 1 : 0,
                    $item->order->created_at->getTimestamp(),
                    $item->id,
                ]);

            $batchesByProduct = [];
            $packsLeft = [];
            $allocated = 0;
            $short = 0;

            foreach ($items as $item) {
                $need = $item->quantity - (int) $item->batches->sum('pivot.quantity');

                if ($need <= 0) {
                    continue;
                }

                $batchesByProduct[$item->product_id] ??= Batch::where('product_id', $item->product_id)
                    ->where('quality_status', QualityStatus::Passed)
                    ->whereDate('best_before', '>=', $date->toDateString())
                    ->where('harvested_at', '<', $date->addDay())
                    ->orderBy('best_before')
                    ->orderBy('harvested_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($batchesByProduct[$item->product_id] as $batch) {
                    if ($need <= 0) {
                        break;
                    }

                    $packsLeft[$batch->id] ??= (int) floor(round($this->remainingKg($batch) / (float) $item->product->pack_weight_kg, 6));
                    $take = min($need, $packsLeft[$batch->id]);

                    if ($take <= 0) {
                        continue;
                    }

                    $already = (int) ($item->batches->firstWhere('id', $batch->id)?->pivot->quantity ?? 0);
                    $item->batches()->syncWithoutDetaching([$batch->id => ['quantity' => $already + $take]]);

                    $packsLeft[$batch->id] -= $take;
                    $need -= $take;
                    $allocated += $take;
                }

                $short += $need;
            }

            return ['allocated_packs' => $allocated, 'short_packs' => $short];
        });
    }

    /**
     * @throws DomainException khi khối lượng không dương hoặc vượt quá phần còn lại của lô
     */
    public function logWaste(Batch $batch, float $kg, WasteReason $reason, User $by, ?string $note = null): WasteLog
    {
        $remaining = $this->remainingKg($batch);

        if ($kg <= 0) {
            throw new DomainException('Khối lượng phải lớn hơn 0.');
        }

        if ($kg > $remaining + 0.001) {
            throw new DomainException('Lô này chỉ còn '.Format::kg($remaining).'.');
        }

        return WasteLog::create([
            'log_date' => today()->toDateString(),
            'product_id' => $batch->product_id,
            'batch_id' => $batch->id,
            'quantity_kg' => $kg,
            'reason' => $reason,
            'note' => $note,
            'created_by' => $by->id,
        ]);
    }
}
