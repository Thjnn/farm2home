<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Trang đầu của mỗi danh sách được nhúng sẵn (không phải chờ API); đổi trang thì Alpine gọi /api/*.
     */
    public function __invoke(Request $request, CatalogService $catalog): View
    {
        return view('public.home', [
            'products' => $catalog->products(max(1, $request->integer('trang', 1)), CatalogController::PRODUCTS_PER_PAGE),
            'baskets' => $catalog->baskets(max(1, $request->integer('trang_gio', 1)), CatalogController::BASKETS_PER_PAGE),
        ]);
    }
}
