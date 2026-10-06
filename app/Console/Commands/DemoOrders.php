<?php

namespace App\Console\Commands;

use App\Models\Forecast;
use App\Services\ForecastService;
use App\Support\ShippingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('demo:orders
    {--count=2000 : Tổng số đơn mô phỏng}
    {--days=84 : Số ngày lịch sử, tính tới hết hôm nay}
    {--customers=150 : Số khách mô phỏng}
    {--seed=2026 : Cùng seed thì ra cùng dữ liệu}
    {--force : Cho phép chạy ở môi trường production}')]
#[Description('Sinh đơn hàng mô phỏng (có nhịp cuối tuần) rồi chạy lại dự báo cho từng ngày đã qua')]
class DemoOrders extends Command
{
    /**
     * Khách mô phỏng nhận ra bằng cả hai dấu này; dữ liệu mẫu và đơn thật không bị đụng tới.
     */
    private const PHONE_PREFIX = '09999';

    private const NOTE = '[mô phỏng]';

    /**
     * Hệ số theo thứ (1 = Thứ Hai … 7 = Chủ Nhật): cuối tuần đông hơn.
     */
    private const WEEKDAY_FACTOR = [1 => 0.8, 2 => 0.7, 3 => 0.8, 4 => 0.9, 5 => 1.1, 6 => 1.8, 7 => 1.4];

    /**
     * Ngày giao D+1…D+6: càng xa càng ít khách đã đặt.
     */
    private const AHEAD_FACTOR = [1 => 0.85, 2 => 0.5, 3 => 0.35, 4 => 0.25, 5 => 0.2, 6 => 0.15];

    private const PRODUCT_POPULARITY = ['RM' => 25, 'CN' => 18, 'CX' => 12, 'MT' => 12, 'RD' => 10, 'XL' => 13, 'HL' => 10];

    /**
     * Số ngày đặt trước ngày giao.
     */
    private const LEAD_DAYS = [1 => 55, 2 => 25, 3 => 10, 4 => 4, 5 => 3, 6 => 3];

    private const CHANNELS = ['zalo' => 50, 'form' => 25, 'website' => 15, 'phone' => 10];

    public function handle(ForecastService $forecasts): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Không chạy ở production nếu thiếu --force.');

            return self::FAILURE;
        }

        mt_srand((int) $this->option('seed'));

        $count = max(1, (int) $this->option('count'));
        $days = max(8, (int) $this->option('days'));
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();
        $start = $today->subDays($days - 1);

        DB::transaction(function () use ($count, $start, $today, $now) {
            $this->deleteSimulated();
            $customers = $this->createCustomers(max(1, (int) $this->option('customers')), $start);
            $this->createOrders($count, $customers, $start, $today, $now);
        });

        // Chạy lại dự báo như thể mỗi ngày đều bấm nút lúc 18:15, chỉ thấy đơn đã có tới lúc đó.
        $rows = 0;
        $runs = 0;
        for ($day = $start->addDays(7); $day->lte($today); $day = $day->addDay()) {
            $rows += $forecasts->run($day, knownAt: $day->setTime(18, 15));
            $runs++;
        }

        Forecast::whereDate('target_date', '>=', $start->toDateString())->update(['actual_kg' => null]);
        $filled = $forecasts->backfillActuals($today->subDay());

        $this->info("Đã sinh {$count} đơn mô phỏng ({$start->format('d/m/Y')} → {$today->addDays(6)->format('d/m/Y')}).");
        $this->info("Đã chạy lại dự báo cho {$runs} ngày ({$rows} dòng), điền số thực tế cho {$filled} dòng.");

        return self::SUCCESS;
    }

    private function deleteSimulated(): void
    {
        $customerIds = DB::table('customers')
            ->where('phone', 'like', self::PHONE_PREFIX.'%')
            ->where('note', self::NOTE)
            ->pluck('id');

        if ($customerIds->isEmpty()) {
            return;
        }

        $orderIds = DB::table('orders')->whereIn('customer_id', $customerIds)->pluck('id');
        $itemIds = DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id');

        DB::table('point_transactions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('feedbacks')->whereIn('customer_id', $customerIds)->orWhereIn('order_id', $orderIds)->delete();
        DB::table('packaging_returns')->whereIn('customer_id', $customerIds)->delete();
        DB::table('order_item_batches')->whereIn('order_item_id', $itemIds)->delete();
        DB::table('order_items')->whereIn('id', $itemIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
        DB::table('subscriptions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customers')->whereIn('id', $customerIds)->delete();
    }

    /**
     * @return list<array{id: int, zone: object, address: ?string}>
     */
    private function createCustomers(int $count, CarbonImmutable $start): array
    {
        $zones = DB::table('delivery_zones')->where('is_active', true)->get()->values();
        $createdAt = $start->subDay()->setTime(9, 0);
        $customers = [];

        for ($i = 1; $i <= $count; $i++) {
            $zone = $zones[mt_rand(0, $zones->count() - 1)];
            $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $address = $zone->pickup_point === null ? "Số {$i} đường mô phỏng" : null;

            $id = DB::table('customers')->insertGetId([
                'name' => "Khách mô phỏng {$number}",
                'phone' => self::PHONE_PREFIX.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'address' => $address,
                'delivery_zone_id' => $zone->id,
                'household_size' => mt_rand(1, 5),
                'source' => $this->pick(self::CHANNELS),
                'data_consent' => true,
                'note' => self::NOTE,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $customers[] = ['id' => $id, 'zone' => $zone, 'address' => $address];
        }

        return $customers;
    }

    /**
     * @param  list<array{id: int, zone: object, address: ?string}>  $customers
     */
    private function createOrders(int $count, array $customers, CarbonImmutable $start, CarbonImmutable $today, CarbonImmutable $now): void
    {
        $products = DB::table('products')->where('is_active', true)->get()->keyBy('id');
        $popularity = $products->mapWithKeys(fn ($product) => [$product->id => self::PRODUCT_POPULARITY[$product->code] ?? 10])->all();
        $baskets = DB::table('baskets')->where('is_active', true)->orderBy('price')->get()->values();
        $basketItems = DB::table('basket_items')->get()->groupBy('basket_id');
        $timeSlots = config('farm2home.order.time_slots');

        $ordersPerDay = $this->spreadOverDays($count, $start, $today);
        $lastCodes = DB::table('orders')
            ->whereBetween('delivery_date', [$start->toDateString(), $today->addDays(6)->toDateString()])
            ->groupBy('delivery_date')
            ->selectRaw('delivery_date, MAX(code) as code')
            ->pluck('code', 'delivery_date');

        $items = [];

        foreach ($ordersPerDay as $date => $orders) {
            $day = CarbonImmutable::parse($date);
            $sequence = isset($lastCodes[$date]) ? (int) substr($lastCodes[$date], -3) : 0;

            for ($n = 0; $n < $orders; $n++) {
                $customer = $customers[mt_rand(0, count($customers) - 1)];
                $zone = $customer['zone'];
                $isPickup = $zone->pickup_point !== null;

                $basket = ($baskets->isNotEmpty() && $this->chance(0.4)) ? $baskets[$this->pick([30, 45, 25]) % $baskets->count()] : null;
                $lines = $basket === null
                    ? $this->singleLines($products, $popularity)
                    : $this->basketLines($basket, $basketItems[$basket->id] ?? collect(), $products);
                $subtotal = $basket === null ? array_sum(array_column($lines, 'line_total')) : (int) $basket->price;
                $shipping = ShippingCalculator::fee((int) $zone->shipping_fee, $zone->free_ship_from === null ? null : (int) $zone->free_ship_from, $isPickup, $subtotal);

                // Đặt trước giờ chốt 18:00 hôm trước, và không ở tương lai.
                $lead = max($this->pick(self::LEAD_DAYS), (int) $today->diffInDays($day));
                $createdAt = $day->subDays($lead)->setTime(mt_rand(7, 17), mt_rand(0, 59));
                $createdAt = $createdAt->min($now->subMinute());

                [$status, $confirmedAt, $deliveredAt] = $this->statusFor($day, $today, $createdAt);
                $paymentMethod = $this->chance(0.3) ? 'bank_transfer' : 'cod';

                $orderId = DB::table('orders')->insertGetId([
                    'code' => 'DH'.$day->format('ymd').'-'.str_pad((string) ++$sequence, 3, '0', STR_PAD_LEFT),
                    'customer_id' => $customer['id'],
                    'delivery_zone_id' => $zone->id,
                    'order_type' => $basket === null ? 'single' : 'basket',
                    'channel' => $this->pick(self::CHANNELS),
                    'delivery_date' => $date,
                    'time_slot' => $timeSlots[mt_rand(0, count($timeSlots) - 1)],
                    'delivery_method' => $isPickup ? 'pickup' : 'home',
                    'delivery_address' => $customer['address'],
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shipping,
                    'discount' => 0,
                    'total' => $subtotal + $shipping,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $status === 'delivered' ? 'paid' : 'unpaid',
                    'status' => $status,
                    'confirmed_at' => $confirmedAt,
                    'delivered_at' => $deliveredAt,
                    'cancel_reason' => $status === 'cancelled' ? 'Khách đổi lịch '.self::NOTE : null,
                    'note' => self::NOTE,
                    'created_at' => $createdAt,
                    'updated_at' => $deliveredAt ?? $confirmedAt ?? $createdAt,
                ]);

                foreach ($lines as $line) {
                    $fulfilled = null;
                    if ($status === 'delivered') {
                        // Khoảng 4% dòng giao thiếu 1 gói (cho KPI tỷ lệ đáp ứng).
                        $fulfilled = $this->chance(0.04) ? $line['quantity'] - 1 : $line['quantity'];
                    }

                    $items[] = $line + [
                        'order_id' => $orderId,
                        'fulfilled_quantity' => $fulfilled,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }
        }

        foreach (array_chunk($items, 500) as $chunk) {
            DB::table('order_items')->insert($chunk);
        }
    }

    /**
     * Chia $count đơn cho từng ngày giao theo trọng số thứ × xu hướng tăng × mức đặt trước.
     *
     * @return array<string, int> ['Y-m-d' => số đơn]
     */
    private function spreadOverDays(int $count, CarbonImmutable $start, CarbonImmutable $today): array
    {
        $historyDays = (int) $start->diffInDays($today);
        $weights = [];

        for ($day = $start; $day->lte($today->addDays(6)); $day = $day->addDay()) {
            $index = (int) $start->diffInDays($day);
            $trend = 0.7 + 0.5 * min(1, $index / max(1, $historyDays));
            $ahead = self::AHEAD_FACTOR[(int) $today->diffInDays($day)] ?? 1.0;
            $weights[$day->toDateString()] = self::WEEKDAY_FACTOR[$day->dayOfWeekIso] * $trend * ($day->gt($today) ? $ahead : 1.0);
        }

        $perDay = array_fill_keys(array_keys($weights), 0);
        for ($i = 0; $i < $count; $i++) {
            $perDay[$this->pick($weights)]++;
        }

        return $perDay;
    }

    /**
     * @param  array<int, int>  $popularity
     * @return list<array{product_id: int, basket_id: null, quantity: int, weight_kg: float, unit_price: int, line_total: int}>
     */
    private function singleLines(Collection $products, array $popularity): array
    {
        $lines = [];

        foreach (range(1, mt_rand(1, min(4, count($popularity)))) as $ignored) {
            $productId = $this->pick($popularity);
            unset($popularity[$productId]);

            $product = $products[$productId];
            $quantity = mt_rand(1, 3);
            $lines[] = [
                'product_id' => $productId,
                'basket_id' => null,
                'quantity' => $quantity,
                'weight_kg' => round($quantity * (float) $product->pack_weight_kg, 3),
                'unit_price' => (int) $product->price,
                'line_total' => $quantity * (int) $product->price,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array{product_id: int, basket_id: int, quantity: int, weight_kg: float, unit_price: int, line_total: int}>
     */
    private function basketLines(object $basket, Collection $basketItems, Collection $products): array
    {
        return $basketItems
            ->filter(fn ($item) => $products->has($item->product_id))
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'basket_id' => $basket->id,
                'quantity' => (int) $item->quantity,
                'weight_kg' => round($item->quantity * (float) $products[$item->product_id]->pack_weight_kg, 3),
                'unit_price' => 0,
                'line_total' => 0,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: ?CarbonImmutable, 2: ?CarbonImmutable}
     */
    private function statusFor(CarbonImmutable $day, CarbonImmutable $today, CarbonImmutable $createdAt): array
    {
        $confirmedAt = $createdAt->addHour();

        if ($day->lt($today)) {
            return $this->chance(0.03)
                ? ['cancelled', null, null]
                : ['delivered', $confirmedAt, $day->setTime(17, 30)];
        }

        if ($day->eq($today)) {
            return ['delivering', $confirmedAt, null];
        }

        return $this->chance(0.6) ? ['pending', null, null] : ['confirmed', $createdAt, null];
    }

    private function chance(float $probability): bool
    {
        return mt_rand() / mt_getrandmax() < $probability;
    }

    /**
     * @param  array<int|string, float|int>  $weights
     */
    private function pick(array $weights): int|string
    {
        $target = mt_rand() / mt_getrandmax() * array_sum($weights);

        foreach ($weights as $key => $weight) {
            $target -= $weight;
            if ($target <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
