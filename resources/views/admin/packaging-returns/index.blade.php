<x-layouts.admin title="Bao bì thu hồi">
    <form method="POST" action="{{ route('admin.packaging-returns.store') }}" class="mb-6 grid gap-4 rounded-xl border border-stone-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
        @csrf
        <x-field name="return_date" label="Ngày thu hồi" type="date" :value="today()->toDateString()" required />
        <x-field name="phone" label="SĐT khách (không bắt buộc)" type="tel" inputmode="numeric" />
        <x-field name="order_code" label="Mã đơn (không bắt buộc)" placeholder="DH261003-001" />
        <x-field name="quantity_returned" label="Số bao bì thu hồi" type="number" min="1" :value="1" required />
        <x-field name="quantity_reused" label="Số tái sử dụng được" type="number" min="0" :value="0" required />
        <x-field name="note" label="Ghi chú" />
        <div class="sm:col-span-2 lg:col-span-3">
            <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Ghi nhận</button>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[560px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Ngày</th>
                    <th class="px-3 py-2 font-medium">Khách</th>
                    <th class="px-3 py-2 font-medium">Đơn</th>
                    <th class="px-3 py-2 text-right font-medium">Thu hồi</th>
                    <th class="px-3 py-2 text-right font-medium">Tái sử dụng</th>
                    <th class="px-3 py-2 font-medium">Ghi chú</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($returns as $row)
                    <tr>
                        <td class="px-3 py-2">{{ $row->return_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $row->customer?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $row->order?->code ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">{{ $row->quantity_returned }}</td>
                        <td class="px-3 py-2 text-right">{{ $row->quantity_reused }}</td>
                        <td class="px-3 py-2">{{ $row->note ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-stone-500">Chưa có bao bì thu hồi nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $returns->links() }}</div>
</x-layouts.admin>
