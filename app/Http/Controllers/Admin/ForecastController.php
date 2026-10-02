<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Services\ForecastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function index(ForecastService $forecasts): View
    {
        $method = ForecastMethod::from(config('farm2home.forecast.default_method'));
        $latestRun = Forecast::max('generated_on');

        $rows = $latestRun === null ? collect() : Forecast::with('product')
            ->whereDate('generated_on', $latestRun)
            ->where('method', $method)
            ->orderBy('target_date')
            ->orderBy('product_id')
            ->get();

        return view('admin.forecasts.index', [
            'method' => $method,
            'latestRun' => $latestRun,
            'byDate' => $rows->groupBy(fn (Forecast $forecast) => $forecast->target_date->toDateString()),
            'accuracy' => $forecasts->accuracy(today()->subDays(14), today()),
            'methods' => ForecastMethod::cases(),
        ]);
    }

    public function run(ForecastService $forecasts): RedirectResponse
    {
        $count = $forecasts->run(today());

        return back()->with('status', "Đã chạy dự báo: {$count} dòng.");
    }

    public function backfill(ForecastService $forecasts): RedirectResponse
    {
        $count = $forecasts->backfillActuals(today());

        return back()->with('status', "Đã cập nhật số thực tế cho {$count} dòng.");
    }
}
