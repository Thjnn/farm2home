<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogPageRequest;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

/**
 * JSON cho các danh sách có phân trang ở trang khách (trang chủ, /rau, đặt hàng).
 */
class CatalogController extends Controller
{
    public const PRODUCTS_PER_PAGE = 6;

    public const BASKETS_PER_PAGE = 4;

    public function products(CatalogPageRequest $request, CatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->products(
            $request->page(),
            $request->limit(self::PRODUCTS_PER_PAGE),
            $request->validated('q'),
            $request->category(),
        ));
    }

    /**
     * Chỉ trả giỏ đang bán; tham số active=true được chấp nhận cho rõ nghĩa.
     */
    public function baskets(CatalogPageRequest $request, CatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->baskets($request->page(), $request->limit(self::BASKETS_PER_PAGE)));
    }
}
