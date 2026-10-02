<x-layouts.admin title="Rau dư cuối ngày">
    <form method="GET" action="{{ route('admin.waste.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày</label>
            <input id="date" type="date" name="date" value="{{ $date }}" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
        </div>
        <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Xem</button>
    </form>

    @foreach (['batch_id', 'quantity_kg', 'reason'] as $field)
        @error($field)
            <p class="mb-2 text-sm text-rose-700" role="alert">{{ $message }}</p>
        @enderror
    @endforeach

    <section class="mb-6">
        <h2 class="mb-1 font-semibold">Lô còn tồn</h2>
        <p class="mb-3 text-sm text-stone-600">Lô còn hạn có thể để lại bán ngày mai; chỉ ghi phần thực sự dư, hỏng, hết hạn hoặc bán giảm giá.</p>
        <div class="space-y-3">
            @forelse ($leftovers as $row)
                <article class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <a href="{{ route('admin.batches.show', $row['batch']) }}" class="font-semibold text-emerald-800 underline">{{ $row['batch']->code }}</a>
                        <span>{{ $row['batch']->product->name }} · {{ $row['batch']->farmer->name }}</span>
                        <span class="text-stone-600">Hạn dùng {{ $row['batch']->best_before?->format('d/m/Y') ?? '—' }}</span>
                        <span class="ml-auto">Còn lại <span class="font-semibold">@kg($row['remaining'])</span></span>
                    </div>
                    <form method="POST" action="{{ route('admin.waste.store') }}" class="mt-3 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="batch_id" value="{{ $row['batch']->id }}">
                        <label class="block">
                            <span class="mb-1 block text-stone-700">Số kg</span>
                            <input type="number" step="0.1" min="0.1" name="quantity_kg" value="{{ $row['remaining'] }}" class="h-11 w-24 rounded-lg border border-stone-300 px-2 text-right">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-stone-700">Lý do</span>
                            <select name="reason" class="h-11 rounded-lg border border-stone-300 bg-white px-2">
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block min-w-40 flex-1">
                            <span class="mb-1 block text-stone-700">Ghi chú</span>
                            <input name="note" class="h-11 w-full rounded-lg border border-stone-300 px-2">
                        </label>
                        <button class="h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Ghi nhận</button>
                    </form>
                </article>
            @empty
                <p class="rounded-lg bg-stone-100 px-4 py-3 text-sm text-stone-700">Không còn lô nào tồn.</p>
            @endforelse
        </div>
    </section>

    <section>
        <h2 class="mb-2 font-semibold">Đã ghi trong ngày</h2>
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th class="px-3 py-2 font-medium">Rau</th>
                        <th class="px-3 py-2 font-medium">Lô</th>
                        <th class="px-3 py-2 text-right font-medium">Khối lượng</th>
                        <th class="px-3 py-2 font-medium">Lý do</th>
                        <th class="px-3 py-2 font-medium">Ghi chú</th>
                        <th class="px-3 py-2 font-medium">Người ghi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-3 py-2">{{ $log->product->name }}</td>
                            <td class="px-3 py-2">{{ $log->batch?->code ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">@kg($log->quantity_kg)</td>
                            <td class="px-3 py-2">{{ $log->reason->label() }}</td>
                            <td class="px-3 py-2">{{ $log->note ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $log->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-stone-500">Chưa ghi rau dư cho ngày này.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
