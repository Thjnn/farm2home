<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Chỉ còn bán 5 loại rau: RM, CX, CN, XL, HL.
     * Mồng tơi và rau dền ngưng bán (giữ lại để đơn cũ vẫn tra được), các giỏ thay bằng rau còn bán.
     */
    public function up(): void
    {
        $ids = DB::table('products')->pluck('id', 'code');

        if (! isset($ids['MT'], $ids['RD'])) {
            return;
        }

        DB::transaction(function () use ($ids) {
            DB::table('products')->whereIn('code', ['MT', 'RD'])->update(['is_active' => false, 'updated_at' => now()]);
            DB::table('products')->where('code', 'CX')->update(['name' => 'Cải xanh', 'updated_at' => now()]);

            $baskets = DB::table('baskets')->pluck('id', 'slug');
            $contents = [
                'gio-mini' => ['RM' => 1, 'CN' => 1, 'CX' => 1, 'HL' => 1],
                'gio-xanh-tiet-kiem' => ['RM' => 1, 'CN' => 1, 'CX' => 1, 'XL' => 1, 'HL' => 1],
                'gio-gia-dinh' => ['RM' => 2, 'CN' => 2, 'CX' => 2, 'XL' => 1, 'HL' => 1],
            ];

            foreach ($contents as $slug => $items) {
                if (! isset($baskets[$slug])) {
                    continue;
                }

                DB::table('basket_items')->where('basket_id', $baskets[$slug])->delete();
                DB::table('basket_items')->insert(collect($items)->map(fn ($quantity, $code) => [
                    'basket_id' => $baskets[$slug], 'product_id' => $ids[$code], 'quantity' => $quantity, 'allow_substitute' => true,
                ])->values()->all());
            }
        });
    }

    /**
     * Data change: not reversible.
     */
    public function down(): void
    {
        //
    }
};
