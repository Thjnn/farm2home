<x-layouts.admin title="Dự báo nhu cầu">
    <x-slot:head>
        @vite('resources/js/dashboard.js')
    </x-slot:head>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.forecasts.run') }}">
            @csrf
            <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Chạy dự báo hôm nay</button>
        </form>
        <form method="POST" action="{{ route('admin.forecasts.backfill') }}">
            @csrf
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Cập nhật số thực tế</button>
        </form>
    </div>

    <nav class="mb-3 flex flex-wrap gap-2" aria-label="Chọn phương pháp">
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

    <p class="mb-4 text-sm text-stone-700">
        Đang xem: <span class="font-semibold">{{ $method->label() }}</span> ·
        @if ($latestRun)
            Lần chạy gần nhất: <span class="font-semibold">{{ \Carbon\Carbon::parse($latestRun)->format('d/m/Y') }}</span>
        @else
            Chưa chạy dự báo lần nào.
        @endif
    </p>

    <section class="mb-6 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-1 font-semibold">Độ chính xác 14 ngày gần nhất (dự báo trước 1 ngày)</h2>
        <p class="mb-2 text-xs text-stone-500">Bảng này luôn so sánh cả 3 phương pháp nên không đổi khi bấm nút chọn. Dòng tô đậm là phương pháp đang xem. Biểu đồ và các bảng theo ngày giao bên dưới thì đổi theo nút.</p>
        @if ($accuracy === [])
            <p class="text-sm text-stone-500">Chưa có dự báo nào đã có số thực tế. Bấm "Cập nhật số thực tế" sau ngày giao.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Phương pháp</th>
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
                            <tr @class(["bg-emerald-50 font-semibold" => $candidate === $method])>
                                <td class="py-2 pr-3">
                                    {{ $candidate->label() }}
                                    @if ($candidate === $bestMethod)
                                        <span class="ml-1 rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800">Sai số thấp nhất</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-right">{{ $row['n'] }}</td>
                                <td class="py-2 pr-3 text-right">@kg($row['mae'])</td>
                                <td class="py-2 pr-3 text-right">{{ $row['mape'] === null ? '—' : number_format($row['mape'], 1, ',', '.').'%' }}</td>
                                <td class="py-2 pr-3 text-right">{{ $row['wape'] === null ? '—' : number_format($row['wape'], 1, ',', '.').'%' }}</td>
                                <td class="py-2 text-right">{{ $row['bias'] > 0 ? '+' : '' }}@kg($row['bias'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm leading-relaxed text-stone-700">
                @php($current = $accuracy[$method->value] ?? null)
                <div class="flex items-start gap-2" aria-live="polite">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white" aria-hidden="true">AI</span>
                    <div class="rounded-2xl rounded-tl-sm bg-white px-3 py-2 shadow-sm ring-1 ring-emerald-100">
                        <p class="mb-1 text-xs font-medium text-emerald-800">Trợ lý dự báo · {{ $method->label() }}</p>
                        @if ($current === null)
                            <p>Phương pháp <strong>{{ $method->label() }}</strong> chưa có dự báo nào đã có số thực tế để chấm điểm trong 14 ngày qua.</p>
                        @else
                            <p>
                                Trong 14 ngày qua mình đã chấm {{ $current['n'] }} lần dự báo của phương pháp <strong>{{ $method->label() }}</strong>.
                                Trung bình mỗi loại rau mỗi ngày nó đoán lệch <strong>@kg($current['mae'])</strong>.
                                Cộng tất cả lại, phần đoán lệch bằng khoảng <strong>@pct($current['wape'])</strong> tổng số rau thực tế đã giao.
                            </p>
                            <p class="mt-1">
                                @if (abs($current['bias']) < 0.1)
                                    Phần đoán dư và đoán thiếu gần như cân nhau, nên nó không nghiêng về phía nào.
                                @elseif ($current['bias'] > 0)
                                    Nó hay đoán <strong>dư</strong> khoảng @kg($current['bias']) mỗi loại mỗi ngày.
                                    Với {{ $products->count() }} loại rau, nếu làm theo nó có thể thu hoạch thừa khoảng @kg($current['bias'] * $products->count()) mỗi ngày.
                                @else
                                    Nó hay đoán <strong>thiếu</strong> khoảng @kg(abs($current['bias'])) mỗi loại mỗi ngày.
                                    Với {{ $products->count() }} loại rau, nếu làm theo nó có thể thiếu khoảng @kg(abs($current['bias']) * $products->count()) rau giao khách mỗi ngày.
                                @endif
                            </p>
                            <p class="mt-1">
                                @if ($method === $bestMethod)
                                    Đây là phương pháp <strong>đoán sát nhất</strong> trong các cách đang so sánh.
                                @elseif ($bestMethod)
                                    Phương pháp đoán sát nhất hiện là <strong>{{ $bestMethod->label() }}</strong> (lệch @pct($accuracy[$bestMethod->value]['wape'])), nên cách này chưa phải lựa chọn tốt nhất.
                                @endif
                                @if ($method === $planMethod)
                                    Kế hoạch thu hoạch đang dùng phương pháp này.
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-stone-600">
                    <li><strong>Dự báo trước 1 ngày</strong>: chỉ chấm các con số được dự báo hôm trước cho ngày giao hôm sau, tức đúng con số dùng để quyết định thu hoạch.</li>
                    <li><strong>Số điểm</strong>: số lần dự báo đã có số thực tế để so (mỗi loại rau × mỗi ngày giao là 1 lần). Càng nhiều điểm thì kết quả càng đáng tin.</li>
                    <li><strong>MAE</strong>: trung bình mỗi lần đoán lệch bao nhiêu kg, không phân biệt đoán dư hay đoán thiếu.</li>
                    <li><strong>MAPE</strong>: trung bình mỗi lần đoán lệch bao nhiêu % so với thực tế. Loại rau bán ít (1–2 kg) chỉ lệch 1 kg cũng thành 50%, nên con số này thường cao hơn thực chất.</li>
                    <li><strong>WAPE</strong>: tổng số kg đoán lệch chia cho tổng số kg thực tế. Đây là thước đo chính để chọn phương pháp. Càng thấp càng tốt.</li>
                    <li><strong>Lệch dư/thiếu</strong>: trung bình của (dự báo − thực tế). Số dương (+) là hay đoán dư, dễ thừa rau. Số âm (−) là hay đoán thiếu, dễ thiếu hàng. Càng gần 0 càng tốt.</li>
                </ul>
            </div>
        @endif
    </section>

    @if ($chart)
        <figure class="mb-6 rounded-xl border border-stone-200 bg-white p-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <figcaption class="text-sm font-medium text-stone-700">Nhu cầu 28 ngày qua và dự báo 7 ngày tới (kg)</figcaption>
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
            <div class="h-64"><canvas id="chart-forecast" role="img" aria-label="Biểu đồ đường: nhu cầu thực tế 28 ngày qua và dự báo 7 ngày tới kèm khoảng thấp–cao"></canvas></div>
        </figure>
        <script type="application/json" id="chart-forecast-data">@json($chart)</script>
    @endif

    @forelse ($byDate as $date => $rows)
        <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="mb-2 font-semibold">Ngày giao {{ str(\Carbon\Carbon::parse($date)->locale('vi')->isoFormat('dddd, DD/MM/YYYY'))->ucfirst() }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Loại rau</th>
                            <th class="py-2 pr-3 text-right font-medium">Đã đặt</th>
                            <th class="py-2 pr-3 text-right font-medium">Dự báo thêm</th>
                            <th class="py-2 pr-3 text-right font-medium">Tổng dự báo</th>
                            <th class="py-2 text-right font-medium">Khoảng</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($rows as $forecast)
                            <tr>
                                <td class="py-2 pr-3">{{ $forecast->product->name }}</td>
                                <td class="py-2 pr-3 text-right">@kg($forecast->confirmed_kg)</td>
                                <td class="py-2 pr-3 text-right">@kg($forecast->predicted_extra_kg)</td>
                                <td class="py-2 pr-3 text-right font-semibold">@kg($forecast->predicted_total_kg)</td>
                                <td class="py-2 text-right text-stone-600">@kg($forecast->lower_kg) – @kg($forecast->upper_kg)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-700">Chưa có dự báo. Bấm "Chạy dự báo hôm nay" để bắt đầu.</p>
    @endforelse
</x-layouts.admin>
