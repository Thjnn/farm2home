<x-layouts.admin title="Cụm giao">
    <p class="mb-4"><a href="{{ route('admin.zones.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm cụm giao</a></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Tên</th>
                    <th class="px-3 py-2 font-medium">Mô tả</th>
                    <th class="px-3 py-2 text-right font-medium">Phí giao</th>
                    <th class="px-3 py-2 text-right font-medium">Miễn phí từ</th>
                    <th class="px-3 py-2 font-medium">Điểm nhận</th>
                    <th class="px-3 py-2 font-medium">Đang dùng</th>
                    <th class="px-3 py-2"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($zones as $zone)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $zone->name }}</td>
                        <td class="px-3 py-2">{{ $zone->description ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">@vnd($zone->shipping_fee)</td>
                        <td class="px-3 py-2 text-right">{{ $zone->free_ship_from === null ? '—' : \App\Support\Format::vnd($zone->free_ship_from) }}</td>
                        <td class="px-3 py-2">{{ $zone->pickup_point ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $zone->is_active ? 'Có' : 'Không' }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.zones.edit', $zone) }}" class="text-emerald-800 underline">Sửa</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
