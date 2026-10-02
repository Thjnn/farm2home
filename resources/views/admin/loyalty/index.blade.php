<x-layouts.admin title="Điểm thành viên">
    <p class="mb-4 text-sm text-stone-600">
        Đơn từ @vnd(config('farm2home.loyalty.min_order_subtotal')) giao xong: +{{ config('farm2home.loyalty.order_points') }} điểm ·
        Trả bao bì: +{{ config('farm2home.loyalty.packaging_points') }} điểm. Đổi quà trên trang của từng đơn.
    </p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[520px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Khách</th>
                    <th class="px-3 py-2 font-medium">Điện thoại</th>
                    <th class="px-3 py-2 text-right font-medium">Đã cộng</th>
                    <th class="px-3 py-2 text-right font-medium">Đã đổi</th>
                    <th class="px-3 py-2 text-right font-medium">Số dư</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-3 py-2"><a href="{{ route('admin.loyalty.show', $customer) }}" class="text-emerald-800 underline">{{ $customer->name }}</a></td>
                        <td class="px-3 py-2">{{ $customer->phone }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $customer->earned }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $customer->earned - (int) $customer->balance }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ (int) $customer->balance }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-stone-500">Chưa có khách nào có điểm.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.admin>
