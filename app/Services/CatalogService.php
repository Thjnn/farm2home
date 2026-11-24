<?php

namespace App\Services;

use App\Enums\ProductCategory;
use App\Models\Basket;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Format;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Danh sách rau và giỏ đang bán cho trang khách, có phân trang.
 * Trang vượt quá tổng số trang được đưa về trang cuối.
 */
class CatalogService
{
    /** Bán chạy = tổng kg đã bán (đơn không huỷ) trong bấy nhiêu ngày gần đây. */
    public const BEST_SELLER_DAYS = 28;

    /**
     * @return array{items: list<array<string, mixed>>, page: int, limit: int, total: int, totalPages: int}
     */
    public function products(int $page, int $limit, ?string $search = null, ?ProductCategory $category = null): array
    {
        $sales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.delivery_date', '>=', today()->subDays(self::BEST_SELLER_DAYS))
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.weight_kg) AS sold_kg');

        $query = Product::query()
            ->where('is_active', true)
            ->when(filled($search), fn (Builder $query) => $query->whereLike('name', '%'.addcslashes(trim($search), '%_\\').'%'))
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->leftJoinSub($sales, 'sales', 'sales.product_id', '=', 'products.id')
            ->select('products.*')
            // id ASC phá thế hoà để giữa các trang không trùng, không sót món.
            ->orderByRaw('COALESCE(sales.sold_kg, 0) DESC')
            ->orderBy('products.id');

        return $this->paginate($query, $page, $limit, fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'unit' => Format::kg($product->pack_weight_kg),
            'category' => $product->category->value,
            'imageUrl' => $product->imageUrl(),
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, limit: int, total: int, totalPages: int}
     */
    public function baskets(int $page, int $limit): array
    {
        $query = Basket::query()
            ->with('items.product')
            ->where('is_active', true)
            ->orderBy('price')
            ->orderBy('id');

        return $this->paginate($query, $page, $limit, fn (Basket $basket) => [
            'id' => $basket->id,
            'name' => $basket->name,
            'price' => $basket->price,
            'description' => $basket->description,
            'items' => $basket->items->map(fn ($item) => [
                'quantity' => $item->quantity,
                'name' => $item->product->name,
                'imageUrl' => $item->product->imageUrl(),
            ])->all(),
        ]);
    }

    /**
     * @param  Closure(Model): array<string, mixed>  $map
     * @return array{items: list<array<string, mixed>>, page: int, limit: int, total: int, totalPages: int}
     */
    private function paginate(Builder $query, int $page, int $limit, Closure $map): array
    {
        $total = (clone $query)->toBase()->getCountForPagination();
        $totalPages = (int) ceil($total / $limit);
        $page = max(1, min($page, $totalPages));

        return [
            'items' => $query->forPage($page, $limit)->get()->map($map)->values()->all(),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
        ];
    }
}
