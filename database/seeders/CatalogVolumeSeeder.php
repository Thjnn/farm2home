<?php

namespace Database\Seeders;

use App\Models\Basket;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Chỉ dùng cho dev/test: nâng danh mục lên 30 món và 10 giỏ để thử phân trang.
 * Chạy sau DemoSeeder; món mẫu (RM, CN…) được gán danh mục, món thiếu thì thêm.
 */
class CatalogVolumeSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const PRODUCTS = [
        'leafy' => ['Rau muống', 'Cải ngọt', 'Cải xanh', 'Xà lách', 'Cải thìa', 'Cải bó xôi', 'Rau lang', 'Xà lách xoong', 'Rau má', 'Bắp cải'],
        'soup' => ['Mồng tơi', 'Rau dền', 'Rau ngót', 'Bầu', 'Bí đao', 'Mướp', 'Cải cúc'],
        'root_fruit' => ['Cà chua', 'Cà rốt', 'Khoai lang', 'Củ cải trắng', 'Dưa leo', 'Đậu bắp', 'Bí đỏ'],
        'herb' => ['Hành lá', 'Ngò rí', 'Húng quế', 'Tía tô', 'Rau răm', 'Thì là'],
    ];

    /** @var list<string> */
    private const BASKETS = ['Giỏ Mini', 'Giỏ Xanh Tiết Kiệm', 'Giỏ Gia Đình', 'Giỏ Canh Chua', 'Giỏ Lẩu', 'Giỏ Ăn Chay', 'Giỏ Văn Phòng', 'Giỏ Gia Vị', 'Giỏ Bữa Sáng', 'Giỏ Cuối Tuần'];

    public function run(): void
    {
        $number = 0;
        foreach (self::PRODUCTS as $category => $names) {
            foreach ($names as $name) {
                $number++;
                $product = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                    ?? Product::factory()->create([
                        'code' => sprintf('S%02d', $number),
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'price' => 8000 + ($number % 6) * 2000,
                    ]);
                $product->update(['category' => $category, 'is_active' => true]);
            }
        }

        $products = Product::where('is_active', true)->orderBy('id')->get();
        foreach (self::BASKETS as $index => $name) {
            if (Basket::where('name', $name)->exists()) {
                continue;
            }
            $basket = Basket::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'price' => 40000 + $index * 5000]);
            foreach ($products->slice($index, 3) as $product) {
                $basket->items()->create(['product_id' => $product->id, 'quantity' => 1, 'allow_substitute' => true]);
            }
        }
    }
}
