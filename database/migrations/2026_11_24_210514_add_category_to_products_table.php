<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Danh mục rau cho bộ lọc ở trang /rau và trang đặt hàng; gán sẵn cho 9 món mẫu, admin sửa lại được.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('category', ['leafy', 'soup', 'root_fruit', 'herb'])->default('leafy')->after('slug');
            $table->index(['is_active', 'category'], 'products_active_category_idx');
        });

        Schema::table('baskets', function (Blueprint $table) {
            $table->index(['is_active', 'price'], 'baskets_active_price_idx');
        });

        foreach (['soup' => ['MT', 'RD'], 'root_fruit' => ['CC'], 'herb' => ['HL']] as $category => $codes) {
            DB::table('products')->whereIn('code', $codes)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('baskets', function (Blueprint $table) {
            $table->dropIndex('baskets_active_price_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_category_idx');
            $table->dropColumn('category');
        });
    }
};
