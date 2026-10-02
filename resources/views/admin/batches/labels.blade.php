<x-layouts.base :title="'Tem lô '.$batch->code" bodyClass="bg-white">
    <div class="mx-auto max-w-3xl p-4">
        <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
            <p class="text-sm text-stone-600">{{ $count }} tem · quét mã sẽ mở {{ route('batches.public', $batch->code) }}</p>
            <button type="button" onclick="window.print()" class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">In</button>
        </div>

        <div class="grid grid-cols-3 gap-2">
            @for ($i = 0; $i < $count; $i++)
                <div class="break-inside-avoid rounded border border-stone-400 p-2 text-center text-xs leading-tight">
                    <div class="mx-auto w-28 [&>svg]:h-auto [&>svg]:w-full">{!! $qrSvg !!}</div>
                    <p class="mt-1 text-sm font-bold">{{ $batch->product->name }}</p>
                    <p class="font-semibold">{{ $batch->code }}</p>
                    <p>Nông hộ: {{ $batch->farmer->name }}</p>
                    @if ($batch->packed_at)
                        <p>Đóng gói: {{ $batch->packed_at->format('d/m/Y') }}</p>
                    @endif
                    @if ($batch->best_before)
                        <p>Dùng trước: {{ $batch->best_before->format('d/m/Y') }}</p>
                    @endif
                    <p class="mt-1 text-[10px] text-stone-600">Quét mã để xem thông tin lô</p>
                </div>
            @endfor
        </div>
    </div>
</x-layouts.base>
