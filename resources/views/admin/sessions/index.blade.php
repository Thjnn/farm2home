<x-layouts.admin title="Đối chiếu buổi giao">
    @php
        $baselineMethod = \App\Enums\ForecastMethod::ExtraAverage;
        $wins = $comparison->countBy('winner');
        $perProduct = $comparison->groupBy(fn (array $row) => $row['product']->name)->map->count();
        $dayLabel = fn ($date) => str(\Carbon\Carbon::parse($date)->locale('vi')->isoFormat('dd DD/MM'))->ucfirst();
        $winnerLabel = ['model' => 'Mô hình', 'baseline' => 'Cách thường', 'tie' => 'Hòa'];
    @endphp

    <section class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 leading-relaxed text-stone-800">
        <h2 class="mb-1 text-lg font-semibold">Dự báo có giúp ích thật không?</h2>
        <p>
            Đơn cho ngày mai chốt lúc {{ config('farm2home.order.cutoff_hour') }}:00 hôm trước, nên lúc lên kế hoạch thu hoạch ngày mai đã biết đủ đơn.
            Dự báo chỉ cần cho phần <strong>khách sẽ đặt thêm</strong>, khi HTX phải biết trước <strong>{{ $leadDays }} ngày</strong> để giữ rau và xếp người thu hoạch.
        </p>
        <p class="mt-1">
            Trang này so <strong>{{ $model->label() }}</strong> (đang dùng cho kế hoạch) với <strong>cách tính thông thường</strong>:
            đơn đã có + mức khách thường đặt thêm ở {{ config('farm2home.forecast.baseline_sessions') }} buổi cùng thứ trước. Cả hai được ghi cùng một lần chạy, trước khi biết kết quả.
        </p>
    </section>

    <form method="GET" action="{{ route('admin.sessions.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="from" class="mb-1 block text-sm font-medium text-stone-700">Từ ngày</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <div>
            <label for="to" class="mb-1 block text-sm font-medium text-stone-700">Đến ngày</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
    </form>

    @foreach (['delivery_date', 'product_id', 'quantity_kg', 'to'] as $field)
        @error($field)
            <p class="mb-2 text-sm text-rose-700" role="alert">{{ $message }}</p>
        @enderror
    @endforeach

    {{-- 1. Tổng kết đối chiếu --}}
    <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-2 font-semibold">Mô hình so với cách tính thông thường</h2>
        @if ($comparison->isEmpty())
            <p class="text-sm text-stone-600">Chưa có buổi nào có đủ cả hai dự báo (làm trước {{ $leadDays }} ngày) và số thực tế trong khoảng này.</p>
        @else
            <p class="leading-relaxed">
                <strong>{{ $comparison->count() }}</strong> lần đối chiếu (mỗi loại rau mỗi buổi là 1 lần):
                mô hình đoán gần hơn <strong>{{ $wins['model'] ?? 0 }}</strong> lần,
                cách thường gần hơn <strong>{{ $wins['baseline'] ?? 0 }}</strong> lần,
                hòa <strong>{{ $wins['tie'] ?? 0 }}</strong> lần.
            </p>
            <p class="mt-1 text-sm text-stone-600">
                Số lần đối chiếu theo loại rau:
                @foreach ($perProduct as $name => $count)
                    {{ $name }} <strong>{{ $count }}</strong>@if (! $loop->last), @endif
                @endforeach
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Cách đoán (phần đặt thêm)</th>
                            <th class="py-2 pr-3 text-right font-medium">Số lần</th>
                            <th class="py-2 pr-3 text-right font-medium">Lệch TB (MAE)</th>
                            <th class="py-2 pr-3 text-right font-medium">WAPE</th>
                            <th class="py-2 text-right font-medium">Dư/thiếu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ([$model, $baselineMethod] as $candidate)
                            @continue(! isset($accuracy[$candidate->value]))
                            @php $row = $accuracy[$candidate->value]; @endphp
                            <tr>
                                <td class="py-2 pr-3">{{ $candidate->label() }}</td>
                                <td class="py-2 pr-3 text-right">{{ $row['n'] }}</td>
                                <td class="py-2 pr-3 text-right">@kg($row['mae'])</td>
                                <td class="py-2 pr-3 text-right">@pct($row['wape'])</td>
                                <td class="py-2 text-right">{{ $row['bias'] > 0 ? '+' : '' }}@kg($row['bias'])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-stone-500">Chỉ chấm phần khách đặt thêm sau lúc dự báo, không chấm phần đơn đã có. Mô hình chỉ đáng dùng nếu lệch ít hơn cách thường qua nhiều buổi.</p>
        @endif
    </section>

    {{-- 2. Từng buổi --}}
    @if ($comparison->isNotEmpty())
        <details class="mb-4 rounded-xl border border-stone-200 bg-white">
            <summary class="flex min-h-12 cursor-pointer items-center px-4 py-3 font-semibold">Chi tiết từng buổi ({{ $comparison->count() }} lần)</summary>
            <div class="overflow-x-auto px-4 pb-4">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Buổi giao</th>
                            <th class="py-2 pr-3 font-medium">Rau</th>
                            <th class="py-2 pr-3 text-right font-medium">Đã đặt lúc đoán</th>
                            <th class="py-2 pr-3 text-right font-medium">Cách thường</th>
                            <th class="py-2 pr-3 text-right font-medium">Mô hình</th>
                            <th class="py-2 pr-3 text-right font-medium">Thực tế đặt thêm</th>
                            <th class="py-2 font-medium">Gần hơn</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($comparison as $row)
                            <tr>
                                <td class="py-2 pr-3">{{ $dayLabel($row['date']) }}</td>
                                <td class="py-2 pr-3">{{ $row['product']->name }}</td>
                                <td class="py-2 pr-3 text-right">@kg($row['confirmed'])</td>
                                <td class="py-2 pr-3 text-right">+@kg($row['baseline'])</td>
                                <td class="py-2 pr-3 text-right">+@kg($row['model'])</td>
                                <td class="py-2 pr-3 text-right font-semibold">+@kg($row['actual'])</td>
                                <td @class(['py-2', 'text-emerald-800' => $row['winner'] === 'model', 'text-amber-800' => $row['winner'] === 'baseline'])>{{ $winnerLabel[$row['winner']] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    {{-- 3. Kết quả từng buổi --}}
    <section class="mb-4 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-1 font-semibold">Kết quả từng buổi</h2>
        <p class="mb-3 text-sm text-stone-600">
            Rau dư lấy từ trang Rau dư. "Hết hàng" là kg khách muốn mua mà không bán được (khách hỏi qua Zalo/điện thoại); hệ thống không tự biết số này, người điều phối ghi tay.
        </p>
        @if ($sessions === [])
            <p class="text-sm text-stone-600">Chưa có buổi giao nào trong khoảng này.</p>
        @else
            @php
                $totals = collect($sessions);
                // Mỗi buổi giao một dòng tóm tắt; bấm để mở từng loại rau (và ô ghi "hết hàng"). Buổi mới nhất mở sẵn.
                $byDate = $totals->groupBy('date')->sortKeysDesc();
                $sum = fn ($rows) => [
                    'requested' => $rows->sum('requested_kg'),
                    'delivered' => $rows->sum('delivered_kg'),
                    'surplus' => $rows->sum('surplus_kg'),
                    'unmet' => $rows->sum('unmet_kg'),
                    'surplus_vnd' => $rows->sum('surplus_vnd'),
                    'missed_vnd' => $rows->sum('missed_margin_vnd'),
                ];
                $grand = $sum($totals);
                $cols = 'grid grid-cols-[auto_1fr_auto_1fr] gap-x-3 gap-y-0.5 sm:grid-cols-[minmax(7rem,1.2fr)_repeat(6,minmax(0,1fr))] sm:items-center';
            @endphp

            <div class="overflow-hidden rounded-lg border border-stone-200 text-sm">
                {{-- Tiêu đề cột (màn lớn) --}}
                <div class="{{ $cols }} hidden border-b border-stone-200 bg-stone-50 px-3 py-2 text-xs font-medium text-stone-600 sm:grid" aria-hidden="true">
                    <span>Buổi giao</span><span class="text-right">Khách yêu cầu</span><span class="text-right">Giao được</span><span class="text-right">Dư/hỏng</span>
                    <span class="text-right">Hết hàng</span><span class="text-right">Tiền rau dư</span><span class="text-right">Lãi bỏ lỡ</span>
                </div>

                @foreach ($byDate as $date => $rows)
                    @php($day = $sum($rows))
                    <details class="group border-b border-stone-100 last:border-b-0" @if ($loop->first) open @endif>
                        <summary class="{{ $cols }} cursor-pointer list-none px-3 py-2.5 hover:bg-stone-50 [&::-webkit-details-marker]:hidden">
                            <span class="col-span-4 flex items-center gap-2 font-semibold sm:col-span-1">
                                <svg class="size-4 shrink-0 text-stone-400 transition-transform group-open:rotate-90 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                {{ $dayLabel($date) }}
                            </span>
                            <span class="text-stone-600 sm:hidden">Yêu cầu</span><span class="text-right tabular-nums">@kg($day['requested'])</span>
                            <span class="text-stone-600 sm:hidden">Giao</span><span class="text-right tabular-nums">@kg($day['delivered'])</span>
                            <span class="text-stone-600 sm:hidden">Dư</span><span @class(['text-right tabular-nums', 'font-semibold text-amber-800' => $day['surplus'] > 0])>@kg($day['surplus'])</span>
                            <span class="text-stone-600 sm:hidden">Hết</span><span @class(['text-right tabular-nums', 'font-semibold text-rose-700' => $day['unmet'] > 0])>@kg($day['unmet'])</span>
                            {{-- Điện thoại: dòng tóm tắt chỉ giữ số kg; tiền xem trong chi tiết buổi và dòng Cộng. --}}
                            <span class="hidden text-right tabular-nums sm:block">@vnd($day['surplus_vnd'])</span>
                            <span class="hidden text-right tabular-nums sm:block">@vnd($day['missed_vnd'])</span>
                        </summary>

                        <div class="overflow-x-auto bg-stone-50/60 px-3 pb-3">
                            <table class="table-cards w-full min-w-[720px] text-left text-sm">
                                <thead class="border-b border-stone-200 text-xs text-stone-600">
                                    <tr>
                                        <th class="py-2 pr-3 font-medium">Rau</th>
                                        <th class="py-2 pr-3 text-right font-medium">Khách yêu cầu</th>
                                        <th class="py-2 pr-3 text-right font-medium">Giao được</th>
                                        <th class="py-2 pr-3 text-right font-medium">Dư/hỏng</th>
                                        <th class="py-2 pr-3 font-medium">Hết hàng (kg)</th>
                                        <th class="py-2 pr-3 text-right font-medium">Tiền rau dư</th>
                                        <th class="py-2 text-right font-medium">Lãi bỏ lỡ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-stone-100">
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td class="py-2 pr-3">{{ $row['product']->name }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">@kg($row['requested_kg'])</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ $row['delivered_kg'] === null ? 'chưa ghi' : \App\Support\Format::kg($row['delivered_kg']) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">@kg($row['surplus_kg'])</td>
                                            <td class="py-2 pr-3">
                                                <form method="POST" action="{{ route('admin.sessions.unmet') }}" class="flex items-center gap-1">
                                                    @csrf
                                                    <input type="hidden" name="delivery_date" value="{{ $row['date'] }}">
                                                    <input type="hidden" name="product_id" value="{{ $row['product']->id }}">
                                                    <label class="sr-only" for="unmet-{{ $date }}-{{ $loop->index }}">Kg hết hàng {{ $row['product']->name }} {{ $row['date'] }}</label>
                                                    <input id="unmet-{{ $date }}-{{ $loop->index }}" type="number" step="0.1" min="0" name="quantity_kg" value="{{ $row['unmet_kg'] + 0 }}" class="h-11 w-20 rounded-lg border border-stone-300 bg-white px-2 text-right">
                                                    <button class="h-11 rounded-lg border border-stone-300 bg-white px-3 text-xs hover:bg-stone-100">Lưu</button>
                                                </form>
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">@vnd($row['surplus_vnd'])</td>
                                            <td class="py-2 text-right tabular-nums">@vnd($row['missed_margin_vnd'])</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach

                {{-- Cộng cả khoảng --}}
                <div class="{{ $cols }} border-t border-stone-300 bg-stone-50 px-3 py-2.5 font-semibold">
                    <span class="col-span-4 sm:col-span-1">Cộng {{ $byDate->count() }} buổi</span>
                    <span class="font-normal text-stone-600 sm:hidden">Yêu cầu</span><span class="text-right tabular-nums">@kg($grand['requested'])</span>
                    <span class="font-normal text-stone-600 sm:hidden">Giao</span><span class="text-right tabular-nums">@kg($grand['delivered'])</span>
                    <span class="font-normal text-stone-600 sm:hidden">Dư</span><span class="text-right tabular-nums">@kg($grand['surplus'])</span>
                    <span class="font-normal text-stone-600 sm:hidden">Hết</span><span class="text-right tabular-nums">@kg($grand['unmet'])</span>
                    <span class="font-normal text-stone-600 sm:hidden">Tiền rau dư</span><span class="text-right tabular-nums">@vnd($grand['surplus_vnd'])</span>
                    <span class="font-normal text-stone-600 sm:hidden">Lãi bỏ lỡ</span><span class="text-right tabular-nums">@vnd($grand['missed_vnd'])</span>
                </div>
            </div>
            <p class="mt-2 text-xs text-stone-500">
                Tiền rau dư = kg dư × giá thu mua. Lãi bỏ lỡ = kg hết hàng × (giá bán mỗi kg − giá thu mua). Lợi ích của dự báo chỉ tính được khi so hai giai đoạn
                và phải trừ chi phí chạy hệ thống, công nhập và kiểm tra dữ liệu.
            </p>
        @endif
    </section>
</x-layouts.admin>
