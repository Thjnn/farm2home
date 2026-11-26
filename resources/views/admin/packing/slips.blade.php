<x-layouts.base title="Phiếu dán túi" bodyClass="bg-white" :back-to-top="false">
    <div class="mx-auto max-w-3xl p-4">
        <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
            <p class="text-sm text-stone-600">{{ $slips->count() }} phiếu · mỗi túi một phiếu, quét mã mở trang lô</p>
            <button type="button" onclick="window.print()" class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">In</button>
        </div>

        @if ($slips->isEmpty())
            <p class="text-center text-stone-500">Chưa có túi nào đã gán lô để in.</p>
        @endif

        <div class="grid grid-cols-2 gap-2">
            @foreach ($slips as $slip)
                <div class="flex break-inside-avoid gap-2 rounded border border-stone-400 p-2 text-xs leading-tight">
                    <div class="w-24 shrink-0 [&>svg]:h-auto [&>svg]:w-full">{!! $slip['qr'] !!}</div>
                    <div class="min-w-0">
                        <p class="text-base font-bold">{{ $slip['product']->name }}</p>
                        <p>{{ $slip['packs'] }} gói · @kg($slip['kg'])</p>
                        <p class="font-mono">{{ $slip['batch']->code }}</p>
                        <p>HTX: {{ $slip['batch']->farmer->name }}</p>
                        <p class="mt-1 font-semibold">Túi {{ $slip['number'] }}/{{ $slip['total'] }} · {{ $slip['order']->code }}</p>
                        <p>{{ $slip['order']->customer->name }}</p>
                        <p>{{ $slip['order']->zone->name }} · {{ $slip['order']->time_slot }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.base>
