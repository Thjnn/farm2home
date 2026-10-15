<x-layouts.admin title="Dự báo nhu cầu">
    <x-slot:head>
        @vite('resources/js/dashboard.js')
    </x-slot:head>

    @php
        $current = $accuracy[$method->value] ?? null;
        $dayName = function (string $date): string {
            $day = \Carbon\Carbon::parse($date)->locale('vi');
            $prefix = match ((int) today()->diffInDays($day, false)) {
                0 => 'Hôm nay · ',
                1 => 'Ngày mai · ',
                2 => 'Ngày kia · ',
                default => '',
            };

            return $prefix.str($day->isoFormat('dddd, DD/MM'))->ucfirst();
        };
        $target = config('farm2home.forecast.target_hit_rate');
        $calendarUrl = fn (array $query) => route('admin.forecasts.index', array_filter($query + ['method' => request('method'), 'product' => $product?->id])).'#lich';
        $dayHitRate = fn (array $entry) => $entry['actual'] === null || $entry['actual'] <= 0 ? null
            : max(0, 100 - abs($entry['predicted'] - $entry['actual']) / $entry['actual'] * 100);
        $selected = $calendar[$selectedDay->toDateString()] ?? null;
    @endphp

    {{-- 1. Máy đoán thế nào, nói ngắn gọn --}}
    <section class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-stone-800">
        <h2 class="mb-1 text-lg font-semibold">Mấy ngày tới cần bao nhiêu rau?</h2>
        <p class="leading-relaxed">
            Máy xem <strong>khách đã đặt bao nhiêu</strong> và <strong>những ngày trước bán được bao nhiêu</strong>,
            rồi đoán mỗi loại rau cần khoảng bao nhiêu kg. Đây chỉ là gợi ý: người điều phối luôn xem lại rồi mới báo cho nông hộ.
        </p>
        <p class="mt-2 text-sm text-stone-600">
            Cách đoán đang xem: <strong>{{ $method->plainExplanation() }}</strong>
            @if ($latestRun)
                · Đoán lần gần nhất ngày <strong>{{ \Carbon\Carbon::parse($latestRun)->format('d/m/Y') }}</strong>
            @endif
        </p>
    </section>

    {{-- 2. Máy đoán có đúng không --}}
    <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-2 font-semibold">Máy đoán có đúng không? (14 ngày qua)</h2>
        @if ($current === null)
            <p class="text-sm text-stone-600">Chưa đủ số liệu để chấm điểm. Sau ngày giao, bấm "Cập nhật số thực tế" bên dưới.</p>
        @else
            @php($hitRate = max(0, 100 - (float) $current['wape']))
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex size-24 shrink-0 flex-col items-center justify-center rounded-full border-4 {{ $hitRate >= config('farm2home.forecast.target_hit_rate') ? 'border-emerald-600' : ($hitRate >= 60 ? 'border-amber-500' : 'border-red-500') }}">
                    <span class="text-2xl font-bold">{{ number_format($hitRate, 0) }}%</span>
                    <span class="text-xs text-stone-600">đoán đúng</span>
                </div>
                <ul class="min-w-0 flex-1 space-y-1 leading-relaxed">
                    <li>Cứ <strong>100 kg</strong> rau thật sự giao, máy đoán đúng khoảng <strong>{{ number_format($hitRate, 0) }} kg</strong>.</li>
                    <li>Mỗi loại rau mỗi ngày, máy đoán lệch trung bình <strong>@kg($current['mae'])</strong>.</li>
                    <li>
                        @if (abs($current['bias']) < 0.1)
                            Máy không hay đoán dư hay đoán thiếu, khá cân bằng.
                        @elseif ($current['bias'] > 0)
                            Máy hay đoán <strong>dư</strong> một chút (khoảng @kg($current['bias']) mỗi loại mỗi ngày), nên cẩn thận kẻo thừa rau.
                        @else
                            Máy hay đoán <strong>thiếu</strong> một chút (khoảng @kg(abs($current['bias'])) mỗi loại mỗi ngày), nên có thể chuẩn bị thêm.
                        @endif
                    </li>
                </ul>
            </div>
            <p class="mt-2 text-xs text-stone-500">Dựa trên {{ $current['n'] }} lần đoán đã có số thật để so (mỗi loại rau mỗi ngày là 1 lần).</p>

            @if ($worstDays !== [])
                <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm leading-relaxed text-stone-800" role="alert">
                    <p class="font-semibold text-amber-900">⚠ Đoán đúng dưới {{ config('farm2home.forecast.target_hit_rate') }}%. Những ngày máy đoán lệch nhiều nhất:</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($worstDays as $day)
                            @php($gap = $day['predicted'] - $day['actual'])
                            <li>
                                <strong>{{ str($day['date']->locale('vi')->isoFormat('dddd DD/MM'))->ucfirst() }}</strong>:
                                máy đoán @kg($day['predicted']), thực tế @kg($day['actual'])
                                ({{ $gap >= 0 ? 'dư' : 'thiếu' }} @kg(abs($gap))).
                                <span class="text-stone-600">
                                    {{ $gap >= 0 ? 'Khách mua ít hơn mọi khi' : 'Khách mua nhiều hơn mọi khi' }}{{ $day['cancelled'] > 0 ? ", có {$day['cancelled']} đơn bị hủy" : '' }}.
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-stone-600">Hãy xem ngày đó có mưa bão, nghỉ lễ, hay đơn chưa được nhập đủ không. Ngày lệch sẽ tự ra khỏi bảng điểm sau 14 ngày.</p>
                </div>
            @endif
        @endif
    </section>

    {{-- 3. Lịch tháng: mỗi ô là một ngày giao --}}
    <section id="lich" class="mb-4 scroll-mt-4 rounded-xl border border-stone-200 bg-white">
        <header class="flex flex-wrap items-center justify-between gap-3 p-4">
            <div>
                <h2 class="text-lg font-semibold">{{ str($month->copy()->locale('vi')->isoFormat('MMMM, YYYY'))->ucfirst() }}</h2>
                <p class="text-sm text-stone-600">Bấm vào một ngày để xem từng loại rau.</p>
            </div>
            <nav class="inline-flex w-full -space-x-px sm:w-auto" aria-label="Đổi tháng">
                <a href="{{ $calendarUrl(['month' => $month->copy()->subMonth()->format('Y-m')]) }}" aria-label="Tháng trước"
                   class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-s-lg border border-stone-300 hover:bg-stone-100">‹</a>
                <a href="{{ $calendarUrl(['day' => today()->toDateString()]) }}"
                   class="inline-flex min-h-11 flex-1 items-center justify-center border border-stone-300 px-4 text-sm hover:bg-stone-100 sm:flex-none">Hôm nay</a>
                <a href="{{ $calendarUrl(['month' => $month->copy()->addMonth()->format('Y-m')]) }}" aria-label="Tháng sau"
                   class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-e-lg border border-stone-300 hover:bg-stone-100">›</a>
            </nav>
        </header>

        <div class="grid grid-cols-7 border-y border-stone-200 bg-stone-50 text-center text-xs font-semibold text-stone-600">
            @foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $weekday)
                <div class="py-2">{{ $weekday }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($calendarDays as $date)
                @php($entry = $calendar[$date->toDateString()] ?? null)
                @php($dayRate = $entry ? $dayHitRate($entry) : null)
                @php($isSelected = $date->isSameDay($selectedDay))
                <a href="{{ $calendarUrl(['month' => $month->format('Y-m'), 'day' => $date->toDateString()]) }}"
                   @if ($isSelected) aria-current="date" @endif
                   @class([
                       'flex min-h-16 flex-col gap-1 border-b border-r border-stone-100 p-1.5 text-xs hover:bg-stone-100 sm:min-h-24 sm:p-2',
                       'bg-stone-50 text-stone-400' => ! $date->isSameMonth($month),
                       'ring-2 ring-inset ring-emerald-700' => $isSelected,
                   ])>
                    <time datetime="{{ $date->toDateString() }}" @class([
                        'flex size-6 items-center justify-center self-end rounded-full',
                        'bg-emerald-700 font-bold text-white' => $date->isToday(),
                    ])>{{ $date->day }}</time>
                    @if ($entry)
                        @if ($entry['actual'] !== null)
                            <span @class([
                                'rounded px-1 py-0.5 leading-tight',
                                'bg-emerald-100 text-emerald-900' => $dayRate !== null && $dayRate >= $target,
                                'bg-amber-100 text-amber-900' => $dayRate !== null && $dayRate < $target && $dayRate >= 60,
                                'bg-red-100 text-red-900' => $dayRate === null || $dayRate < 60,
                            ])>
                                <span class="font-semibold">@kg($entry['actual'])</span>
                                <span class="hidden sm:block">đoán @kg($entry['predicted'])</span>
                            </span>
                        @else
                            <span class="rounded border border-dashed border-emerald-400 px-1 py-0.5 leading-tight text-emerald-900">
                                <span class="font-semibold">@kg($entry['predicted'])</span>
                                <span class="hidden sm:block">máy đoán</span>
                            </span>
                        @endif
                    @endif
                </a>
            @endforeach
        </div>

        <ul class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-3 text-xs text-stone-600">
            <li><span class="mr-1 inline-block size-2.5 rounded-sm border border-dashed border-emerald-400" aria-hidden="true"></span>Máy đoán (chưa giao)</li>
            <li><span class="mr-1 inline-block size-2.5 rounded-sm bg-emerald-100" aria-hidden="true"></span>Đã giao, đoán đúng từ {{ $target }}%</li>
            <li><span class="mr-1 inline-block size-2.5 rounded-sm bg-amber-100" aria-hidden="true"></span>Đúng 60–{{ $target }}%</li>
            <li><span class="mr-1 inline-block size-2.5 rounded-sm bg-red-100" aria-hidden="true"></span>Lệch nhiều</li>
        </ul>

        <div class="border-t border-stone-200 p-4">
            <h3 class="mb-2 font-semibold">{{ $dayName($selectedDay->toDateString()) }}</h3>
            @if ($selected === null)
                <p class="text-sm text-stone-600">Ngày này chưa có dự báo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-stone-200 text-stone-600">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Loại rau</th>
                                <th class="py-2 pr-3 text-right font-medium">Khách đã đặt</th>
                                <th class="py-2 pr-3 text-right font-medium">Máy đoán</th>
                                <th class="py-2 text-right font-medium">Thực tế</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($selected['items'] as $forecast)
                                <tr>
                                    <td class="py-2 pr-3">{{ $forecast->product->name }}</td>
                                    <td class="py-2 pr-3 text-right">@kg($forecast->confirmed_kg)</td>
                                    <td class="py-2 pr-3 text-right font-semibold">@kg($forecast->predicted_total_kg)</td>
                                    <td class="py-2 text-right">{{ $forecast->actual_kg === null ? '—' : \App\Support\Format::kg($forecast->actual_kg) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-stone-200 font-semibold">
                            <tr>
                                <td class="py-2 pr-3">Tổng</td>
                                <td class="py-2 pr-3 text-right">@kg($selected['confirmed'])</td>
                                <td class="py-2 pr-3 text-right">@kg($selected['predicted'])</td>
                                <td class="py-2 text-right">{{ $selected['actual'] === null ? '—' : \App\Support\Format::kg($selected['actual']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-2 text-xs text-stone-500">Số máy đoán là lần đoán gần nhất trước ngày giao (đoán ngày {{ $selected['items']->max('generated_on')->format('d/m') }}).</p>
            @endif
        </div>
    </section>

    {{-- 4. Biểu đồ --}}
    @if ($chart)
        <figure class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <figcaption>
                    <span class="font-semibold">Rau đã bán và rau máy đoán (kg)</span>
                    <span class="block text-xs text-stone-500">Đường liền: số đã bán thật 4 tuần qua. Đường đứt: máy đoán 7 ngày tới. Vùng tô màu: có thể ít hơn hoặc nhiều hơn trong khoảng này.</span>
                </figcaption>
                <form method="GET" action="{{ route('admin.forecasts.index') }}">
                    <input type="hidden" name="method" value="{{ $method->value }}">
                    <label class="sr-only" for="chart-product">Loại rau</label>
                    <select id="chart-product" name="product" onchange="this.form.submit()"
                            class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 text-sm">
                        <option value="">Tất cả loại rau</option>
                        @foreach ($products as $item)
                            <option value="{{ $item->id }}" @selected($product?->id === $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm">Xem</button></noscript>
                </form>
            </div>
            <div class="h-64"><canvas id="chart-forecast" role="img" aria-label="Biểu đồ đường: rau đã bán 4 tuần qua và rau máy đoán 7 ngày tới"></canvas></div>
        </figure>
        <script type="application/json" id="chart-forecast-data">@json($chart)</script>
    @endif

    {{-- 5. Từng ngày giao: mỗi loại rau cần bao nhiêu --}}
    @forelse ($byDate as $date => $rows)
        <details class="group mb-3 rounded-xl border border-stone-200 bg-white" @if ($loop->first) open @endif>
            <summary class="flex min-h-12 cursor-pointer items-center justify-between gap-2 px-4 py-3 font-semibold">
                <span>{{ $dayName($date) }}</span>
                <span class="text-sm font-normal text-stone-600">Cần khoảng <strong class="text-stone-900">@kg($rows->sum('predicted_total_kg'))</strong> rau</span>
            </summary>
            <ul class="grid gap-3 px-4 pb-4 sm:grid-cols-2">
                @foreach ($rows as $forecast)
                    @php($total = (float) $forecast->predicted_total_kg)
                    @php($orderedShare = $total > 0 ? round((float) $forecast->confirmed_kg / $total * 100) : 0)
                    <li class="rounded-lg border border-stone-200 p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="font-medium">{{ $forecast->product->name }}</span>
                            <span class="text-xl font-bold text-emerald-800">@kg($total)</span>
                        </div>
                        <div class="my-2 flex h-3 overflow-hidden rounded-full bg-emerald-100" aria-hidden="true">
                            <div class="bg-emerald-700" style="width: {{ $orderedShare }}%"></div>
                        </div>
                        <p class="text-sm leading-relaxed text-stone-700">
                            <span class="inline-block size-2.5 rounded-full bg-emerald-700" aria-hidden="true"></span> Khách đã đặt: <strong>@kg($forecast->confirmed_kg)</strong><br>
                            <span class="inline-block size-2.5 rounded-full bg-emerald-200" aria-hidden="true"></span> Đoán khách đặt thêm: <strong>@kg($forecast->predicted_extra_kg)</strong>
                        </p>
                        <p class="mt-1 text-xs text-stone-500">Có thể từ @kg($forecast->lower_kg) đến @kg($forecast->upper_kg).</p>
                    </li>
                @endforeach
            </ul>
        </details>
    @empty
        <p class="rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-700">Chưa có dự báo. Bấm "Chạy dự báo hôm nay" để bắt đầu.</p>
    @endforelse

    {{-- 6. Nút thao tác --}}
    <div class="my-4 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.forecasts.run') }}">
            @csrf
            <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Chạy dự báo hôm nay</button>
        </form>
        <form method="POST" action="{{ route('admin.forecasts.backfill') }}">
            @csrf
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Cập nhật số thực tế</button>
        </form>
    </div>

    {{-- 7. Chi tiết kỹ thuật: gập lại, chỉ người điều phối cần --}}
    <details class="rounded-xl border border-stone-200 bg-white" @if (request()->has('method')) open @endif>
        <summary class="flex min-h-12 cursor-pointer items-center px-4 py-3 font-semibold">So sánh các cách đoán (chi tiết kỹ thuật)</summary>
        <div class="px-4 pb-4">
            <nav class="mb-3 flex flex-wrap gap-2" aria-label="Chọn cách đoán">
                @foreach ($methods as $candidate)
                    @continue(in_array($candidate, [\App\Enums\ForecastMethod::Manual, \App\Enums\ForecastMethod::TreeRegression], true))
                    <a href="{{ route('admin.forecasts.index', array_filter(['method' => $candidate->value, 'product' => $product?->id])) }}"
                       @class([
                           'inline-flex min-h-11 items-center rounded-lg border px-3 text-sm',
                           'border-emerald-700 bg-emerald-700 font-semibold text-white' => $candidate === $method,
                           'border-stone-300 bg-white hover:bg-stone-100' => $candidate !== $method,
                       ])
                       @if ($candidate === $method) aria-current="page" @endif>
                        {{ $candidate->label() }}@if ($candidate === $planMethod)<span class="ml-1 text-xs font-normal">(đang dùng cho kế hoạch)</span>@endif
                    </a>
                @endforeach
            </nav>

            @if ($accuracy === [])
                <p class="text-sm text-stone-500">Chưa có dự báo nào đã có số thực tế.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="border-b border-stone-200 text-stone-600">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Cách đoán</th>
                                <th class="py-2 pr-3 text-right font-medium">Đoán đúng</th>
                                <th class="py-2 pr-3 text-right font-medium">Số điểm</th>
                                <th class="py-2 pr-3 text-right font-medium">MAE</th>
                                <th class="py-2 pr-3 text-right font-medium">MAPE</th>
                                <th class="py-2 pr-3 text-right font-medium">WAPE</th>
                                <th class="py-2 text-right font-medium">Lệch dư/thiếu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($methods as $candidate)
                                @continue(! isset($accuracy[$candidate->value]))
                                @php($row = $accuracy[$candidate->value])
                                <tr @class(['bg-emerald-50 font-semibold' => $candidate === $method])>
                                    <td class="py-2 pr-3">
                                        {{ $candidate->label() }}
                                        @if ($candidate === $bestMethod)
                                            <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">Đoán sát nhất</span>
                                        @endif
                                        <span class="block text-xs font-normal text-stone-500">{{ $candidate->plainExplanation() }}</span>
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ $row['wape'] === null ? '—' : number_format(max(0, 100 - $row['wape']), 0).'%' }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $row['n'] }}</td>
                                    <td class="py-2 pr-3 text-right">@kg($row['mae'])</td>
                                    <td class="py-2 pr-3 text-right">@pct($row['mape'])</td>
                                    <td class="py-2 pr-3 text-right">@pct($row['wape'])</td>
                                    <td class="py-2 text-right">{{ $row['bias'] > 0 ? '+' : '' }}@kg($row['bias'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-stone-600">
                    <li><strong>Đoán đúng</strong> = 100% − WAPE. Chỉ chấm con số đoán trước 1 ngày, tức con số dùng để quyết định thu hoạch.</li>
                    <li><strong>MAE</strong>: mỗi lần đoán lệch trung bình bao nhiêu kg.</li>
                    <li><strong>MAPE</strong>: mỗi lần lệch bao nhiêu % so với thực tế. Rau bán ít (1–2 kg) lệch 1 kg đã thành 50%, nên số này thường cao.</li>
                    <li><strong>WAPE</strong>: tổng kg đoán lệch chia tổng kg thực tế. Đây là thước đo chính để chọn cách đoán; càng thấp càng tốt.</li>
                    <li><strong>Lệch dư/thiếu</strong>: dương (+) là hay đoán dư, âm (−) là hay đoán thiếu. Càng gần 0 càng tốt.</li>
                </ul>
            @endif
        </div>
    </details>
</x-layouts.admin>
