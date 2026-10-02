<x-layouts.admin title="Giỏ rau">
    <p class="mb-4"><a href="{{ route('admin.baskets.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm giỏ rau</a></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Tên</th>
                    <th class="px-3 py-2 text-right font-medium">Giá</th>
                    <th class="px-3 py-2 font-medium">Số người</th>
                    <th class="px-3 py-2 font-medium">Thành phần</th>
                    <th class="px-3 py-2 font-medium">Đang bán</th>
                    <th class="px-3 py-2"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($baskets as $basket)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $basket->name }}</td>
                        <td class="px-3 py-2 text-right">@vnd($basket->price)</td>
                        <td class="px-3 py-2">{{ $basket->household_min }}–{{ $basket->household_max }}</td>
                        <td class="px-3 py-2">{{ $basket->items->map(fn ($item) => $item->quantity.' × '.$item->product->name)->join(', ') }}</td>
                        <td class="px-3 py-2">{{ $basket->is_active ? 'Có' : 'Không' }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.baskets.edit', $basket) }}" class="text-emerald-800 underline">Sửa</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
