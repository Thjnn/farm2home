<?php

namespace App\Http\Controllers\Public;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Trang /rau: tìm theo tên, lọc theo danh mục, 6 món mỗi trang. q, danh_muc, trang nằm trên URL.
 */
class ProductPageController extends Controller
{
    public function __invoke(Request $request, CatalogService $catalog): View
    {
        $search = Str::limit(trim((string) $request->query('q')), 50, '');
        $category = ProductCategory::tryFrom((string) $request->query('danh_muc'));

        return view('public.products.index', [
            'products' => $catalog->products(max(1, $request->integer('trang', 1)), CatalogController::PRODUCTS_PER_PAGE, $search, $category),
            'search' => $search,
            'category' => $category,
            'categories' => ProductCategory::cases(),
        ]);
    }
}
