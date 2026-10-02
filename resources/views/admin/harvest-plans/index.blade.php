<x-layouts.admin title="Kế hoạch thu hoạch">
    <form method="POST" action="{{ route('admin.harvest-plans.generate') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4">
        @csrf
        <div>
            <label for="delivery_date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
            <input id="delivery_date" type="date" name="delivery_date" value="{{ old('delivery_date', $defaultDate) }}" class="min-h-11 rounded-lg border border-stone-300 px-3">
            @error('delivery_date')
                <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </div>
        <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Tạo gợi ý thu hoạch</button>
        <p class="basis-full text-sm text-stone-600">Hệ thống chỉ gợi ý. Bạn xem lại, chỉnh và chốt trước khi gửi cho nông hộ.</p>
    </form>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[480px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Ngày giao</th>
                    <th class="px-3 py-2 font-medium">Trạng thái</th>
                    <th class="px-3 py-2 text-right font-medium">Số dòng</th>
                    <th class="px-3 py-2 text-right font-medium">Biên an toàn</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($plans as $plan)
                    <tr>
                        <td class="px-3 py-2"><a href="{{ route('admin.harvest-plans.show', $plan) }}" class="font-semibold text-emerald-800 underline">{{ $plan->delivery_date->format('d/m/Y') }}</a></td>
                        <td class="px-3 py-2"><x-status-badge :status="$plan->status" /></td>
                        <td class="px-3 py-2 text-right">{{ $plan->items_count }}</td>
                        <td class="px-3 py-2 text-right">{{ (float) $plan->safety_margin_pct }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-stone-500">Chưa có kế hoạch nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $plans->links() }}</div>
</x-layouts.admin>
