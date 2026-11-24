<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ForecastMethod;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Services\KpiService;
use App\Services\TodayTasksService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Việc cần làm hôm nay ở đầu trang, bên dưới là KPI pilot (mặc định 28 ngày gần nhất).
     */
    public function __invoke(Request $request, KpiService $kpi, TodayTasksService $today): View
    {
        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $to = CarbonImmutable::parse($range['to'] ?? today());
        $from = CarbonImmutable::parse($range['from'] ?? $to->subDays(27));

        return view('admin.dashboard', [
            'todayTasks' => $today->tasks(CarbonImmutable::today()),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'forecast' => $kpi->forecast($from, $to),
            'customers' => $kpi->customers($from, $to),
            'economics' => $kpi->economics($from, $to),
            'farmers' => $kpi->farmers($from, $to),
            'environment' => $kpi->environment($from, $to),
            'loyalty' => $kpi->loyalty($from, $to),
            'methods' => ForecastMethod::cases(),
            'expiringCertificates' => Farmer::certificatesNeedingRenewal(),
        ]);
    }
}
