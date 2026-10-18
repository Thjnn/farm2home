<x-layouts.admin :title="'Lô '.$batch->code">
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm lg:col-span-1">
            <div class="mb-2"><x-status-badge :status="$batch->quality_status" /></div>
            <dl class="space-y-1">
                <div><dt class="inline text-stone-600">Rau:</dt> <dd class="inline font-medium">{{ $batch->product->name }}</dd></div>
                <div><dt class="inline text-stone-600">Nông hộ:</dt> <dd class="inline">{{ $batch->farmer->name }}</dd></div>
                <div><dt class="inline text-stone-600">Đã nhận:</dt> <dd class="inline font-medium">@kg($batch->quantity_kg)</dd></div>
                <div><dt class="inline text-stone-600">Còn lại:</dt> <dd class="inline font-medium">@kg($remainingKg)</dd></div>
                <div><dt class="inline text-stone-600">Thu hoạch:</dt> <dd class="inline">{{ $batch->harvested_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="inline text-stone-600">Nhận hàng:</dt> <dd class="inline">{{ $batch->received_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="inline text-stone-600">Hạn dùng:</dt> <dd class="inline">{{ $batch->best_before?->format('d/m/Y') ?? '—' }}</dd></div>
                <div><dt class="inline text-stone-600">Phản hồi:</dt> <dd class="inline">{{ $batch->feedbacks->count() }}</dd></div>
            </dl>

            <form method="GET" action="{{ route('admin.batches.labels', $batch) }}" target="_blank" class="mt-4 flex items-end gap-2 border-t border-stone-200 pt-4">
                <div>
                    <label for="count" class="mb-1 block text-stone-700">Số tem</label>
                    <input id="count" type="number" name="count" value="12" min="1" max="200" class="h-11 w-20 rounded-lg border border-stone-300 px-2 text-right">
                </div>
                <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">In tem QR</button>
            </form>
            @if ($batch->quality_status === \App\Enums\QualityStatus::Passed)
                <p class="mt-3"><a href="{{ $batch->publicUrl() }}" target="_blank" rel="noopener" class="text-emerald-800 underline">Xem trang QR công khai</a></p>
            @else
                <p class="mt-3 text-xs text-stone-500">Trang QR và tem chỉ dùng được khi lô đã đạt kiểm tra.</p>
            @endif

            <form method="POST" action="{{ route('admin.batches.photo', $batch) }}" enctype="multipart/form-data" class="mt-4 border-t border-stone-200 pt-4">
                @csrf
                <h3 class="mb-2 font-medium">Ảnh sản phẩm của lô (hiện trên trang QR)</h3>
                @if ($batch->photo)
                    <img src="{{ $batch->photoUrl() }}" alt="Ảnh {{ $batch->product->name }} lô {{ $batch->code }}" class="mb-2 aspect-[4/3] w-full rounded-lg object-cover">
                @endif
                {{-- Trên điện thoại, capture mở thẳng camera sau; chọn xong là tự gửi. --}}
                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-emerald-700 px-4 font-semibold text-emerald-800 hover:bg-emerald-50">
                    {{ $batch->photo ? 'Chụp lại ảnh' : 'Chụp ảnh lô hàng' }}
                    <input type="file" name="photo" accept="image/*" capture="environment" class="sr-only" onchange="this.form.submit()">
                </label>
                @error('photo')
                    <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </form>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4 lg:col-span-2">
            <h2 class="mb-3 font-semibold">Sơ chế, đóng gói, kiểm chất lượng</h2>
            <form method="POST" action="{{ route('admin.batches.update', $batch) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <div>
                    <label for="quality_status" class="mb-1 block text-sm font-medium text-stone-700">Kết quả kiểm tra</label>
                    <select id="quality_status" name="quality_status" class="block min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3">
                        @foreach ($qualityStatuses as $status)
                            <option value="{{ $status->value }}" @selected(old('quality_status', $batch->quality_status->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    @error('quality_status')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>
                <x-field name="qc_note" label="Ghi chú kiểm tra" :value="$batch->qc_note" />
                <x-field name="processed_at" label="Thời điểm sơ chế" type="datetime-local" :value="$batch->processed_at?->format('Y-m-d\TH:i')" />
                <x-field name="packed_at" label="Thời điểm đóng gói" type="datetime-local" :value="$batch->packed_at?->format('Y-m-d\TH:i')" />
                <div class="sm:col-span-2">
                    <x-field name="processing_note" label="Cách sơ chế (hiện trên trang QR)" :value="$batch->processing_note" />
                </div>
                <div class="sm:col-span-2">
                    <button class="min-h-11 rounded-lg bg-emerald-700 px-6 font-semibold text-white hover:bg-emerald-800">Lưu</button>
                </div>
            </form>
        </section>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
            <h2 class="mb-2 font-semibold">Đơn đã gán</h2>
            @forelse ($batch->orderItems as $item)
                <p class="flex justify-between border-b border-stone-100 py-1 last:border-0">
                    <a href="{{ route('admin.orders.show', $item->order) }}" class="text-emerald-800 underline">{{ $item->order->code }}</a>
                    <span>{{ $item->pivot->quantity }} gói</span>
                </p>
            @empty
                <p class="text-stone-500">Chưa gán cho đơn nào.</p>
            @endforelse
        </section>
        <section class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
            <h2 class="mb-2 font-semibold">Rau dư đã ghi</h2>
            @forelse ($batch->wasteLogs as $log)
                <p class="flex justify-between border-b border-stone-100 py-1 last:border-0">
                    <span>{{ $log->log_date->format('d/m/Y') }} · {{ $log->reason->label() }}</span>
                    <span>@kg($log->quantity_kg)</span>
                </p>
            @empty
                <p class="text-stone-500">Chưa ghi rau dư.</p>
            @endforelse
        </section>
    </div>
</x-layouts.admin>
