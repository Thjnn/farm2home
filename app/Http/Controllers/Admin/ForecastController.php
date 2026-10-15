<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Models\Product;
use App\Services\ForecastService;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function index(Request $request, ForecastService $forecasts): View
    {
        $planMethod = ForecastMethod::from(config('farm2home.forecast.default_method'));
        $method = ForecastMethod::tryFrom((string) $request->query('method')) ?? $planMethod;
        $products = Product::where('is_active', true)->orderBy('id')->get();
        $product = $products->firstWhere('id', (int) $request->query('product'));
        $latestRun = Forecast::max('generated_on');

        $rows = $latestRun === null ? collect() : Forecast::with('product')
            ->whereDate('generated_on', $latestRun)
            ->where('method', $method)
            ->orderBy('target_date')
            ->orderBy('product_id')
            ->get();

        $accuracy = $forecasts->accuracy(today()->subDays(14), today());
        $wape = $accuracy[$method->value]['wape'] ?? null;
        $worstDays = $wape !== null && 100 - $wape < config('farm2home.forecast.target_hit_rate')
            ? $forecasts->worstDays(today()->subDays(14), today(), $method)
            : [];
        // Lịch tháng: ?month=2026-10, ?day=2026-10-03 (mặc định hôm nay).
        $day = rescue(fn () => Carbon::createFromFormat('!Y-m-d', (string) $request->query('day')), null, false)
            ?? today();
        $month = rescue(fn () => Carbon::createFromFormat('!Y-m', (string) $request->query('month')), null, false)
            ?? $day->copy()->startOfMonth();
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $bestMethod = collect($accuracy)->filter(fn (array $row) => $row['wape'] !== null)->sortBy('wape')->keys()->first();

        return view('admin.forecasts.index', [
            'method' => $method,
            'planMethod' => $planMethod,
            'latestRun' => $latestRun,
            'byDate' => $rows->groupBy(fn (Forecast $forecast) => $forecast->target_date->toDateString()),
            'accuracy' => $accuracy,
            'worstDays' => $worstDays,
            'bestMethod' => $bestMethod === null ? null : ForecastMethod::from($bestMethod),
            'methods' => ForecastMethod::cases(),
            'products' => $products,
            'product' => $product,
            'month' => $month,
            'selectedDay' => $day,
            'calendarDays' => collect(CarbonPeriod::create($gridStart, $gridEnd)),
            'calendar' => $forecasts->calendar($gridStart, $gridEnd, $method),
            'chart' => $latestRun === null ? null : $forecasts->chartSeries(Carbon::parse($latestRun), $method, $product),
        ]);
    }

    public function run(ForecastService $forecasts): RedirectResponse
    {
        $count = $forecasts->run(today());

        return back()->with('status', "Đã chạy dự báo: {$count} dòng.");
    }

    public function backfill(ForecastService $forecasts): RedirectResponse
    {
        // Hôm nay chưa giao xong: điền bây giờ sẽ chốt một số thực tế còn thiếu (chỉ điền dòng còn trống).
        $count = $forecasts->backfillActuals(today()->subDay());

        return back()->with('status', "Đã cập nhật số thực tế cho {$count} dòng.");
    }
}
