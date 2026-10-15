<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * Những gì mới kể từ mốc `since` (unix giây) mà trang quản trị đang giữ; trả kèm mốc mới để lần sau hỏi tiếp.
     */
    public function __invoke(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['since' => ['required', 'integer', 'min:0']]);

        $now = now();
        // Tab bỏ quên lâu thì chỉ báo việc trong 1 giờ gần nhất.
        $since = Carbon::createFromTimestamp($data['since'], config('app.timezone'))->max($now->copy()->subHour());

        return response()->json([
            'now' => $now->getTimestamp(),
            'badges' => $notifications->badges(),
            'events' => $notifications->events($since, $now),
        ]);
    }
}
