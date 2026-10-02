<?php

namespace App\Services;

use App\Enums\QualityStatus;
use App\Models\Batch;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
}
