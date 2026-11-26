<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow the request only when the signed-in user has one of the given roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        if (! in_array($user->role->value, $roles, true)) {
            // Người giao lỡ mở trang quản trị: đưa về màn hình của họ thay vì báo lỗi.
            abort_unless($user->isShipper(), 403);

            return redirect()->route('shipper.index');
        }

        return $next($request);
    }
}
