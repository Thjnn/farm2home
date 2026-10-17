@php
    $isEmpty = $economics['delivered_orders'] === 0 && $forecast['plan_items'] === 0;
    $weeks = array_keys($environment['received_by_week']);
    $wasteChart = [
        'labels' => array_map(fn ($week) => 'Tuần '.substr($week, -2), $weeks),
        'received' => array_values($environment['received_by_week']),
        'waste' => array_map(fn ($week) => $environment['waste_by_week'][$week] ?? 0, $weeks),
    ];
    $revenueChart = [
        'labels' => array_map(fn ($day) => \Carbon\Carbon::parse($day)->format('d/m'), array_keys($economics['revenue_by_day'])),
        'values' => array_values($economics['revenue_by_day']),
    ];
@endphp
<x-layouts.admin title="Tổng quan">
    <x-slot:head>
        @vite('resources/js/dashboard.js')
    </x-slot:head>

    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="mb-1 block text-sm font-medium text-stone-700">Từ ngày</label>
            <input id="from" type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <div>
            <label for="to" class="mb-1 block text-sm font-medium text-stone-700">Đến ngày</label>
            <input id="to" type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
        @error('to')
            <p class="basis-full text-sm text-rose-700">{{ $message }}</p>
        @enderror
    </form>

    @if ($isEmpty)
        <p class="mb-4 rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-700">Chưa có dữ liệu trong khoảng này.</p>
    @endif

    @if ($expiringCertificates->isNotEmpty())
        <section class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" aria-label="Chứng nhận cần gia hạn">
            <p class="font-semibold">Chứng nhận cần gia hạn</p>
            <ul class="mt-1 space-y-0.5">
                @foreach ($expiringCertificates as $farmer)
                    @php($daysLeft = $farmer->certificateDaysLeft())
                    <li>
                        {{ $farmer->code }} · {{ $farmer->name }}: {{ $farmer->certificate_name ?? 'chứng nhận' }}
                        {{ $daysLeft < 0 ? 'đã hết hạn '.abs($daysLeft).' ngày, trang QR không còn hiện chứng nhận' : ($daysLeft === 0 ? 'hết hạn hôm nay' : 'còn '.$daysLeft.' ngày') }}
                        ({{ $farmer->certificate_expires_at->format('d/m/Y') }})
                        @if (auth()->user()->role === \App\Enums\UserRole::Admin)
                            · <a href="{{ route('admin.farmers.edit', $farmer) }}" class="underline">cập nhật</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="space-y-6">
        <section aria-labelledby="kpi-economics">
            <h2 id="kpi-economics" class="mb-2 font-semibold">Kinh tế</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-stat label="Doanh thu (đơn đã giao)" :value="\App\Support\Format::vnd($economics['revenue'])" :hint="$economics['delivered_orders'].' đơn'" />
                <x-stat label="Giá trị đơn trung bình" :value="$economics['avg_order_value'] === null ? '—' : \App\Support\Format::vnd($economics['avg_order_value'])" />
                <x-stat label="Biên gộp" :value="\App\Support\Format::pct($economics['gross_margin_pct'])" hint="Tiền hàng đã giao trừ giá mua toàn bộ hàng đã nhận" />
                <x-stat label="Phí giao thu / đơn" :value="$economics['shipping_per_order'] === null ? '—' : \App\Support\Format::vnd($economics['shipping_per_order'])" hint="Chưa có số chi phí giao thực tế" />
            </div>
            @if ($revenueChart['values'] !== [])
                <figure class="mt-3 rounded-xl border border-stone-200 bg-white p-4">
                    <figcaption class="mb-2 text-sm font-medium text-stone-700">Doanh thu theo ngày giao</figcaption>
                    <div class="h-56"><canvas id="chart-revenue" role="img" aria-label="Biểu đồ đường: doanh thu theo ngày giao"></canvas></div>
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-stone-600">Xem dạng bảng</summary>
                        <table class="mt-2 w-full max-w-xs text-left">
                            <thead><tr class="text-stone-600"><th class="py-1 font-medium">Ngày</th><th class="py-1 text-right font-medium">Doanh thu</th></tr></thead>
                            <tbody>
                                @foreach ($economics['revenue_by_day'] as $day => $total)
                                    <tr><td class="py-0.5">{{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}</td><td class="py-0.5 text-right">@vnd($total)</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </figure>
            @endif
        </section>

        <section aria-labelledby="kpi-environment">
            <h2 id="kpi-environment" class="mb-2 font-semibold">Môi trường</h2>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <x-stat label="Rau dư" :value="\App\Support\Format::kg($environment['waste_kg'])" :hint="'trên '.\App\Support\Format::kg($environment['received_kg']).' đã nhận'" />
                <x-stat label="Tỷ lệ rau dư / hàng nhận" :value="\App\Support\Format::pct($environment['waste_pct'])" />
                <x-stat label="Bao bì thu hồi" :value="$environment['packaging_returned']" :hint="$environment['packaging_reused'].' tái sử dụng được'" />
                <x-stat label="Tỷ lệ tái sử dụng bao bì" :value="\App\Support\Format::pct($environment['packaging_reuse_pct'])" />
            </div>
            @if ($weeks !== [])
                <figure class="mt-3 rounded-xl border border-stone-200 bg-white p-4">
                    <figcaption class="mb-2 text-sm font-medium text-stone-700">Hàng nhận và rau dư theo tuần (kg)</figcaption>
                    <div class="h-56"><canvas id="chart-waste" role="img" aria-label="Biểu đồ cột: hàng nhận từ nông hộ và rau dư theo tuần"></canvas></div>
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-stone-600">Xem dạng bảng</summary>
                        <table class="mt-2 w-full max-w-md text-left">
                            <thead><tr class="text-stone-600"><th class="py-1 font-medium">Tuần</th><th class="py-1 text-right font-medium">Nhận</th><th class="py-1 text-right font-medium">Rau dư</th><th class="py-1 text-right font-medium">Tỷ lệ</th></tr></thead>
                            <tbody>
                                @foreach ($weeks as $week)
                                    @php($received = $environment['received_by_week'][$week])
                                    @php($wasted = $environment['waste_by_week'][$week] ?? 0)
                                    <tr>
                                        <td class="py-0.5">{{ $week }}</td>
                                        <td class="py-0.5 text-right">@kg($received)</td>
                                        <td class="py-0.5 text-right">@kg($wasted)</td>
                                        <td class="py-0.5 text-right">@pct($received > 0 ? round(100 * $wasted / $received, 1) : null)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </figure>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="kpi-customers">
                <h2 id="kpi-customers" class="mb-2 font-semibold">Khách hàng</h2>
                <div class="grid grid-cols-2 gap-3">
                    <x-stat label="Tỷ lệ giao đủ" :value="\App\Support\Format::pct($customers['fill_rate_pct'])" />
                    <x-stat label="Khách mua lại" :value="\App\Support\Format::pct($customers['repeat_rate_pct'])" :hint="$customers['repeat_customers'].' / '.$customers['customers'].' khách'" />
                    <x-stat label="Điểm đánh giá" :value="$customers['avg_rating'] === null ? '—' : number_format($customers['avg_rating'], 2, ',', '.')" hint="thang 5" />
                    <x-stat label="Phản hồi đã xử lý" :value="$customers['feedback_resolved'].' / '.$customers['feedback_total']" />
                    <x-stat label="Điểm đã cộng" :value="$loyalty['points_earned']" hint="đơn giao xong và trả bao bì" />
                    <x-stat label="Điểm đã đổi" :value="$loyalty['points_redeemed']" :hint="$loyalty['redemptions'].' lượt đổi quà'" />
                </div>
            </section>

            <section aria-labelledby="kpi-farmers">
                <h2 id="kpi-farmers" class="mb-2 font-semibold">Nông hộ</h2>
                <div class="grid grid-cols-2 gap-3">
                    <x-stat label="Nông hộ đang hợp tác" :value="$farmers['active_farmers']" />
                    <x-stat label="Sản lượng được xác nhận trước" :value="\App\Support\Format::pct($farmers['advance_confirmed_pct'])" />
                </div>
            </section>
        </div>

        <section aria-labelledby="kpi-forecast">
            <h2 id="kpi-forecast" class="mb-2 font-semibold">Dự báo</h2>
            <div class="grid gap-3 lg:grid-cols-3">
                <x-stat label="Số lần operator điều chỉnh" :value="$forecast['adjusted_items'].' / '.$forecast['plan_items']" hint="dòng kế hoạch có số chốt khác số gợi ý" />
                <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white p-4 lg:col-span-2">
                    @if ($forecast['accuracy'] === [])
                        <p class="text-sm text-stone-500">Chưa có dự báo nào đã có số thực tế trong khoảng này.</p>
                    @else
                        <table class="w-full min-w-[420px] text-left text-sm">
                            <thead class="border-b border-stone-200 text-stone-600">
                                <tr>
                                    <th class="py-1.5 pr-3 font-medium">Phương pháp</th>
                                    <th class="py-1.5 pr-3 text-right font-medium">Số điểm</th>
                                    <th class="py-1.5 pr-3 text-right font-medium">MAE</th>
                                    <th class="py-1.5 pr-3 text-right font-medium">MAPE</th>
                                    <th class="py-1.5 text-right font-medium">WAPE</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($methods as $method)
                                    @continue(! isset($forecast['accuracy'][$method->value]))
                                    @php($row = $forecast['accuracy'][$method->value])
                                    <tr>
                                        <td class="py-1.5 pr-3">{{ $method->label() }}</td>
                                        <td class="py-1.5 pr-3 text-right">{{ $row['n'] }}</td>
                                        <td class="py-1.5 pr-3 text-right">@kg($row['mae'])</td>
                                        <td class="py-1.5 pr-3 text-right">@pct($row['mape'])</td>
                                        <td class="py-1.5 text-right">@pct($row['wape'])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-2 text-xs text-stone-500">Dự báo trước 1 ngày. WAPE = tổng sai số tuyệt đối / tổng thực tế.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="chart-waste-data">@json($wasteChart)</script>
    <script type="application/json" id="chart-revenue-data">@json($revenueChart)</script>
</x-layouts.admin>
