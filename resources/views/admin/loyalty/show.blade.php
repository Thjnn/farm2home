<x-layouts.admin :title="'Điểm của '.$customer->name">
    <p class="mb-4 text-sm">{{ $customer->phone }} · Số dư: <span class="font-semibold">{{ $points }} điểm</span></p>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th class="px-3 py-2 font-medium">Thời gian</th>
                    <th class="px-3 py-2 font-medium">Lý do</th>
                    <th class="px-3 py-2 font-medium">Đơn</th>
                    <th class="px-3 py-2 font-medium">Quà</th>
                    <th class="px-3 py-2 text-right font-medium">Điểm</th>
                    <th class="px-3 py-2 font-medium">Người duyệt</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($transactions as $row)
                    <tr>
                        <td class="px-3 py-2">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $row->reason->label() }}</td>
                        <td class="px-3 py-2">
                            @if ($row->order)
                                <a href="{{ route('admin.orders.show', $row->order) }}" class="text-emerald-800 underline">{{ $row->order->code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ \App\Support\Reward::find($row->reward)?->label ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ $row->points > 0 ? '+'.$row->points : $row->points }}</td>
                        <td class="px-3 py-2">{{ $row->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-stone-500">Khách này chưa có giao dịch điểm.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>

    <p class="mt-4 text-sm"><a href="{{ route('admin.loyalty.index') }}" class="text-emerald-800 underline">← Điểm thành viên</a></p>
</x-layouts.admin>
