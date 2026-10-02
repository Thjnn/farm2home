<x-layouts.admin title="Lô hàng">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="GET" action="{{ route('admin.batches.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày thu hoạch</label>
                <input id="date" type="date" name="date" value="{{ $date }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
            </div>
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
        </form>
        <form method="POST" action="{{ route('admin.batches.allocate') }}" class="ml-auto">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Gán lô cho đơn ngày này</button>
        </form>
    </div>
    <p class="mb-4 text-sm text-stone-600">Lô được tạo khi bấm "Nhận" trong kế hoạch thu hoạch. Chỉ lô đã đạt kiểm tra mới được gán cho đơn và in tem.</p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Mã lô</th>
                    <th class="px-3 py-2 font-medium">Rau</th>
                    <th class="px-3 py-2 font-medium">Nông hộ</th>
                    <th class="px-3 py-2 text-right font-medium">Đã nhận</th>
                    <th class="px-3 py-2 text-right font-medium">Còn lại</th>
                    <th class="px-3 py-2 font-medium">Chất lượng</th>
                    <th class="px-3 py-2 font-medium">Hạn dùng</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($batches as $batch)
                    <tr>
                        <td class="px-3 py-2"><a href="{{ route('admin.batches.show', $batch) }}" class="font-semibold text-emerald-800 underline">{{ $batch->code }}</a></td>
                        <td class="px-3 py-2">{{ $batch->product->name }}</td>
                        <td class="px-3 py-2">{{ $batch->farmer->name }}</td>
                        <td class="px-3 py-2 text-right">@kg($batch->quantity_kg)</td>
                        <td class="px-3 py-2 text-right">@kg($remaining[$batch->id])</td>
                        <td class="px-3 py-2"><x-status-badge :status="$batch->quality_status" /></td>
                        <td class="px-3 py-2">{{ $batch->best_before?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-stone-500">Chưa có lô nào thu hoạch ngày này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
