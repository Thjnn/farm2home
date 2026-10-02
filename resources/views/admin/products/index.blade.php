<x-layouts.admin title="Sản phẩm">
    <p class="mb-4"><a href="{{ route('admin.products.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm sản phẩm</a></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Mã</th>
                    <th class="px-3 py-2 font-medium">Tên</th>
                    <th class="px-3 py-2 text-right font-medium">Gói</th>
                    <th class="px-3 py-2 text-right font-medium">Giá bán / gói</th>
                    <th class="px-3 py-2 text-right font-medium">Giá mua / kg</th>
                    <th class="px-3 py-2 text-right font-medium">Hạn dùng</th>
                    <th class="px-3 py-2 font-medium">Đang bán</th>
                    <th class="px-3 py-2"><span class="sr-only">Thao tác</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($products as $product)
                    <tr>
                        <td class="px-3 py-2 font-semibold">{{ $product->code }}</td>
                        <td class="px-3 py-2">{{ $product->name }}</td>
                        <td class="px-3 py-2 text-right">@kg($product->pack_weight_kg)</td>
                        <td class="px-3 py-2 text-right">@vnd($product->price)</td>
                        <td class="px-3 py-2 text-right">@vnd($product->cost_price_per_kg)</td>
                        <td class="px-3 py-2 text-right">{{ $product->shelf_life_days }} ngày</td>
                        <td class="px-3 py-2">{{ $product->is_active ? 'Có' : 'Không' }}</td>
                        <td class="px-3 py-2 text-right"><a href="{{ route('admin.products.edit', $product) }}" class="text-emerald-800 underline">Sửa</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
