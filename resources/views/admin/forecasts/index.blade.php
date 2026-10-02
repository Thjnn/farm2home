<x-layouts.admin title="Dự báo nhu cầu">
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

    <p class="mb-4 text-sm text-stone-700">
        Phương pháp đang dùng: <span class="font-semibold">{{ $method->label() }}</span> ·
        @if ($latestRun)
            Lần chạy gần nhất: <span class="font-semibold">{{ \Carbon\Carbon::parse($latestRun)->format('d/m/Y') }}</span>
        @else
            Chưa chạy dự báo lần nào.
        @endif
    </p>

    <section class="mb-6 rounded-xl border border-stone-200 bg-white p-4">
        <h2 class="mb-1 font-semibold">Độ chính xác 14 ngày gần nhất (dự báo trước 1 ngày)</h2>
        @if ($accuracy === [])
            <p class="text-sm text-stone-500">Chưa có dự báo nào đã có số thực tế. Bấm "Cập nhật số thực tế" sau ngày giao.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b border-stone-200 text-stone-600">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Phương pháp</th>
                            <th class="py-2 pr-3 text-right font-medium">Số điểm</th>
                            <th class="py-2 pr-3 text-right font-medium">MAE</th>
                            <th class="py-2 pr-3 text-right font-medium">MAPE</th>
                            <th class="py-2 text-right font-medium">WAPE</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($methods as $candidate)
                            @continue(! isset($accuracy[$candidate->value]))
                            @php($row = $accuracy[$candidate->value])
                            <tr>
                                <td class="py-2 pr-3">{{ $candidate->label() }}</td>
                                <td class="py-2 pr-3 text-right">{{ $row['n'] }}</td>
                                <td class="py-2 pr-3 text-right">@kg($row['mae'])</td>
                                <td class="py-2 pr-3 text-right">{{ $row['mape'] === null ? '—' : number_format($row['mape'], 1, ',', '.').'%' }}</td>
                                <td class="py-2 text-right">{{ $row['wape'] === null ? '—' : number_format($row['wape'], 1, ',', '.').'%' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-stone-500">WAPE = tổng sai số tuyệt đối / tổng thực tế. MAPE bị phóng đại khi khối lượng mỗi loại rau nhỏ.</p>
        @endif
    </section>

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
