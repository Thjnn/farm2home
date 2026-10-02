@php($weekdays = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'])
<x-layouts.admin title="Giỏ định kỳ">
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <form method="POST" action="{{ route('admin.subscriptions.generate') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4">
            @csrf
            <div>
                <label for="date" class="mb-1 block text-sm font-medium text-stone-700">Ngày giao</label>
                <input id="date" type="date" name="date" value="{{ old('date', $defaultDate) }}" class="min-h-11 rounded-lg border border-stone-300 px-3">
                @error('date')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="min-h-11 rounded-lg border border-stone-300 bg-white px-4 hover:bg-stone-100">Sinh đơn cho ngày này</button>
        </form>
        <a href="{{ route('admin.subscriptions.create') }}" class="ml-auto inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm giỏ định kỳ</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Khách</th>
                    <th class="px-3 py-2 font-medium">Giỏ</th>
                    <th class="px-3 py-2 font-medium">Tần suất</th>
                    <th class="px-3 py-2 font-medium">Thứ giao</th>
                    <th class="px-3 py-2 font-medium">Khung giờ</th>
                    <th class="px-3 py-2 font-medium">Hiệu lực</th>
                    <th class="px-3 py-2 font-medium">Trạng thái</th>
                    <th class="px-3 py-2"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td class="px-3 py-2">{{ $subscription->customer->name }}<span class="block text-xs text-stone-500">{{ $subscription->customer->phone }}</span></td>
                        <td class="px-3 py-2">{{ $subscription->basket->name }}</td>
                        <td class="px-3 py-2">{{ $subscription->frequency->label() }}</td>
                        <td class="px-3 py-2">{{ $weekdays[$subscription->delivery_weekday] ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $subscription->time_slot }}</td>
                        <td class="px-3 py-2">{{ $subscription->start_date->format('d/m/Y') }} – {{ $subscription->end_date?->format('d/m/Y') ?? 'chưa hẹn ngày dừng' }}</td>
                        <td class="px-3 py-2"><x-status-badge :status="$subscription->status" /></td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.subscriptions.edit', $subscription) }}" class="text-emerald-800 underline">Sửa</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-stone-500">Chưa có giỏ định kỳ nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
</x-layouts.admin>
