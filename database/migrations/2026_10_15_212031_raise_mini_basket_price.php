<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Giỏ Mini 45.000đ → 50.000đ để đủ ngưỡng cộng điểm đơn (D27). Đơn cũ giữ giá lúc đặt.
     */
    public function up(): void
    {
        DB::table('baskets')->where('slug', 'gio-mini')->where('price', 45000)->update(['price' => 50000, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('baskets')->where('slug', 'gio-mini')->where('price', 50000)->update(['price' => 45000, 'updated_at' => now()]);
    }
};
