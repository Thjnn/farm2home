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
    // Chọn nhanh khoảng ngày: luôn kết thúc hôm nay; mặc định của controller là 28 ngày ("4 tuần").
    $todayDate = today()->toDateString();
    $quickRanges = collect([7 => '7 ngày', 28 => '4 tuần', 84 => '12 tuần'])->map(fn ($label, $days) => [
        'label' => $label,
        'from' => $rangeFrom = today()->subDays($days - 1)->toDateString(),
        'active' => $from === $rangeFrom && $to === $todayDate,
    ]);
    // Phương pháp có WAPE thấp nhất = phương pháp đoán sát nhất (cùng cách chọn với trang Dự báo).
    $bestMethodKey = collect($forecast['accuracy'])->filter(fn (array $row) => $row['wape'] !== null)->sortBy('wape')->keys()->first();
    $bestMethodRow = $bestMethodKey === null ? null : $forecast['accuracy'][$bestMethodKey];
    // Các nhóm số phụ bên dưới biểu đồ: [nhãn, giá trị, dòng giải thích].
    $groups = [
        ['id' => 'kpi-economics', 'title' => 'Kinh tế', 'rows' => [
            ['Giá trị đơn trung bình', $economics['avg_order_value'] === null ? '—' : \App\Support\Format::vnd($economics['avg_order_value']), null],
            ['Biên gộp', \App\Support\Format::pct($economics['gross_margin_pct']), 'Tiền hàng đã giao trừ giá mua toàn bộ hàng đã nhận'],
            ['Phí giao thu / đơn', $economics['shipping_per_order'] === null ? '—' : \App\Support\Format::vnd($economics['shipping_per_order']), 'Chưa có số chi phí giao thực tế'],
        ]],
        ['id' => 'kpi-environment', 'title' => 'Môi trường', 'rows' => [
            ['Rau dư', \App\Support\Format::kg($environment['waste_kg']), 'trên '.\App\Support\Format::kg($environment['received_kg']).' đã nhận'],
            ['Bao bì thu hồi', $environment['packaging_returned'], $environment['packaging_reused'].' tái sử dụng được'],
            ['Tỷ lệ tái sử dụng bao bì', \App\Support\Format::pct($environment['packaging_reuse_pct']), null],
        ]],
        ['id' => 'kpi-customers', 'title' => 'Khách hàng', 'rows' => [
            ['Khách mua lại', \App\Support\Format::pct($customers['repeat_rate_pct']), $customers['repeat_customers'].' / '.$customers['customers'].' khách'],
            ['Điểm đánh giá', $customers['avg_rating'] === null ? '—' : number_format($customers['avg_rating'], 2, ',', '.'), 'thang 5'],
            ['Phản hồi đã xử lý', $customers['feedback_resolved'].' / '.$customers['feedback_total'], null],
        ]],
        ['id' => 'kpi-farmers', 'title' => 'HTX', 'rows' => [
            ['HTX đang hợp tác', $farmers['active_farmers'], null],
            ['Sản lượng được xác nhận trước', \App\Support\Format::pct($farmers['advance_confirmed_pct']), null],
        ]],
        ['id' => 'kpi-loyalty', 'title' => 'Tích điểm', 'rows' => [
            ['Điểm đã cộng', $loyalty['points_earned'], 'đơn giao xong và trả bao bì'],
            ['Điểm đã đổi', $loyalty['points_redeemed'], null],
            ['Lượt đổi quà', $loyalty['redemptions'], null],
        ]],
    ];
@endphp
<x-layouts.admin title="Tổng quan">
    <x-slot:head>
        @vite('resources/js/dashboard.js')
    </x-slot:head>

    <x-slot:subtitle>Số liệu từ {{ \Carbon\Carbon::parse($from)->format('d/m') }} đến {{ \Carbon\Carbon::parse($to)->format('d/m') }}</x-slot:subtitle>

    <x-slot:actions>
        <nav aria-label="Chọn nhanh khoảng ngày" class="flex rounded-lg border border-stone-300 bg-white p-0.5">
            @foreach ($quickRanges as $range)
                <a href="{{ route('admin.dashboard', ['from' => $range['from'], 'to' => $todayDate]) }}" @if ($range['active']) aria-current="true" @endif
                    @class([
                        'inline-flex min-h-11 items-center rounded-md px-3 text-sm whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 motion-reduce:transition-none',
                        'bg-emerald-700 font-semibold text-white' => $range['active'],
                        'text-stone-700 hover:bg-stone-100' => ! $range['active'],
                    ])>{{ $range['label'] }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-center gap-2">
            <label for="from" class="sr-only">Từ ngày</label>
            <input id="from" type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-2 text-sm">
            <span class="text-stone-500" aria-hidden="true">–</span>
            <label for="to" class="sr-only">Đến ngày</label>
            <input id="to" type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-2 text-sm">
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 text-sm hover:bg-stone-100">Xem</button>
            @error('to')
                <p class="basis-full text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </form>
    </x-slot:actions>

    {{-- Việc cần làm: mỗi thẻ dẫn tới đúng trang; còn việc thì nổi màu, xong thì mờ. --}}
    <section aria-labelledby="today-heading" class="mb-8">
        <h2 id="today-heading" class="mb-3 text-lg font-semibold">Hôm nay <span class="font-normal text-stone-500">· {{ str(today()->locale('vi')->isoFormat('dddd, DD/MM'))->ucfirst() }}</span></h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($todayTasks as $task)
                <a href="{{ $task['url'] }}" data-task="{{ $task['key'] }}"
                    @class([
                        'group flex min-h-28 flex-col rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 motion-reduce:transition-none motion-reduce:hover:translate-y-0',
                        'border-amber-300 bg-amber-50' => $task['count'] > 0,
                        'border-stone-200 bg-white' => $task['count'] === 0,
                    ])>
                    <span class="text-sm font-medium text-stone-700">{{ $task['label'] }}</span>
                    <span @class(['mt-1 text-3xl font-bold tabular-nums', 'text-amber-900' => $task['count'] > 0, 'text-stone-400' => $task['count'] === 0])>{{ $task['count'] }}</span>
                    <span class="mt-auto flex items-center justify-between gap-2 pt-1 text-xs text-stone-600">
                        <span class="truncate">{{ $task['detail'] }}</span>
                        <span aria-hidden="true" class="transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none">→</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($expiringCertificates->isNotEmpty())
        <section class="mb-8 flex gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" aria-label="Chứng nhận cần gia hạn">
            <svg class="mt-0.5 size-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            <div class="min-w-0">
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
            </div>
        </section>
    @endif

    <h2 class="mb-3 text-lg font-semibold">Chỉ số pilot</h2>

    @if ($isEmpty)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-4 text-sm text-stone-700">
            <p><span class="font-medium text-stone-900">Chưa có dữ liệu trong khoảng này.</span> Số liệu sẽ hiện khi có đơn đã giao hoặc kế hoạch thu hoạch; thử chọn khoảng ngày dài hơn.</p>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-medium text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Xem đơn hàng →</a>
        </div>
    @endif

    {{-- Hàng số chính: 4 số trả lời "pilot có đang hiệu quả không". Không lặp lại ở các nhóm bên dưới. --}}
    <div class="mb-6 grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 lg:grid-cols-4">
        <x-stat size="lg" tone="emerald" data-reveal label="Tỷ lệ rau dư / hàng nhận" :value="\App\Support\Format::pct($environment['waste_pct'])" hint="càng thấp càng ít rau bỏ" />
        <x-stat size="lg" data-reveal label="Sai số dự báo (WAPE)" :value="$bestMethodRow ? \App\Support\Format::pct($bestMethodRow['wape']) : '—'"
            :hint="$bestMethodKey ? \App\Enums\ForecastMethod::from($bestMethodKey)->label().' · càng thấp máy đoán càng sát' : 'chưa có dự báo đã có số thực tế'" />
        <x-stat size="lg" data-reveal label="Tỷ lệ giao đủ" :value="\App\Support\Format::pct($customers['fill_rate_pct'])" hint="phần gói khách đặt đã giao đủ" />
        <x-stat size="lg" data-reveal label="Doanh thu (đơn đã giao)" :value="\App\Support\Format::vnd($economics['revenue'])" :hint="$economics['delivered_orders'].' đơn đã giao'" />
    </div>

    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-2">
            @if ($revenueChart['values'] !== [])
                <figure data-reveal class="min-w-0 rounded-xl border border-stone-200 bg-white p-4">
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
            @if ($weeks !== [])
                <figure data-reveal class="min-w-0 rounded-xl border border-stone-200 bg-white p-4">
                    <figcaption class="mb-2 text-sm font-medium text-stone-700">Hàng nhận và rau dư theo tuần (kg)</figcaption>
                    <div class="h-56"><canvas id="chart-waste" role="img" aria-label="Biểu đồ cột: hàng nhận từ HTX và rau dư theo tuần"></canvas></div>
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
        </div>

        {{-- 3 nhóm đầu thành 3 cột từ lg; HTX + Tích điểm ghép một hàng 2 cột. --}}
        @foreach ([array_slice($groups, 0, 3), array_slice($groups, 3)] as $groupRow)
            <div @class(['grid gap-4', 'md:grid-cols-2 lg:grid-cols-3' => count($groupRow) === 3, 'md:grid-cols-2' => count($groupRow) === 2])>
                @foreach ($groupRow as $group)
                    <section aria-labelledby="{{ $group['id'] }}" data-reveal class="min-w-0 rounded-xl border border-stone-200 bg-white p-4">
                        <h2 id="{{ $group['id'] }}" class="mb-1 text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $group['title'] }}</h2>
                        <dl class="divide-y divide-stone-100">
                            @foreach ($group['rows'] as [$rowLabel, $rowValue, $rowHint])
                                <div class="flex items-baseline justify-between gap-3 py-2">
                                    <dt class="min-w-0 text-sm text-stone-700">
                                        {{ $rowLabel }}
                                        @if ($rowHint)
                                            <span class="block text-xs text-stone-500">{{ $rowHint }}</span>
                                        @endif
                                    </dt>
                                    <dd class="shrink-0 text-lg font-semibold text-stone-900 tabular-nums">{{ $rowValue }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
        @endforeach

        <section aria-labelledby="kpi-forecast">
            <h2 id="kpi-forecast" class="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Dự báo</h2>
            <div class="grid gap-3 lg:grid-cols-3">
                <x-stat data-reveal label="Số lần operator điều chỉnh" :value="$forecast['adjusted_items'].' / '.$forecast['plan_items']" hint="dòng kế hoạch có số chốt khác số gợi ý" />
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
                                    <tr @class(['bg-emerald-50' => $method->value === $bestMethodKey])>
                                        <td class="py-1.5 pr-3">
                                            {{ $method->label() }}
                                            @if ($method->value === $bestMethodKey)
                                                <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">Sai số thấp nhất</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 pr-3 text-right">{{ $row['n'] }}</td>
                                        <td class="py-1.5 pr-3 text-right">@kg($row['mae'])</td>
                                        <td class="py-1.5 pr-3 text-right">@pct($row['mape'])</td>
                                        <td class="py-1.5 text-right">@pct($row['wape'])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-2 text-xs text-stone-500">Dự báo làm trước buổi giao {{ config('farm2home.forecast.evaluation_lead_days') }} ngày, chỉ chấm phần khách đặt thêm sau lúc đoán. WAPE = tổng sai số / tổng kg đặt thêm. <a href="{{ route('admin.sessions.index') }}" class="underline">Đối chiếu từng buổi</a></p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="chart-waste-data">@json($wasteChart)</script>
    <script type="application/json" id="chart-revenue-data">@json($revenueChart)</script>
</x-layouts.admin>
