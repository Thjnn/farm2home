<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Services\KpiService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Pilot KPI dashboard. Defaults to the last 28 days ending today.
     */
    public function __invoke(Request $request, KpiService $kpi): View
    {
        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($range['to'] ?? today());
        $from = CarbonImmutable::parse($range['from'] ?? $to->subDays(27));

        return view('admin.dashboard', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'forecast' => $kpi->forecast($from, $to),
            'customers' => $kpi->customers($from, $to),
            'economics' => $kpi->economics($from, $to),
            'farmers' => $kpi->farmers($from, $to),
            'environment' => $kpi->environment($from, $to),
            'loyalty' => $kpi->loyalty($from, $to),
            'methods' => ForecastMethod::cases(),
        ]);
    }
}
