<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nhớ địa chỉ trang danh sách (kèm bộ lọc) mở gần nhất, để link "← Quay lại" ở trang chi tiết / form trả về đúng chỗ.
 */
class RememberListUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route()?->getName();
        if ($request->isMethod('GET') && $response->isSuccessful() && $route !== null && str_ends_with($route, '.index')) {
            $request->session()->put(self::key($route), $request->fullUrl());
        }

        return $response;
    }

    public static function key(string $listRoute): string
    {
        return 'list_url.'.$listRoute;
    }
}
