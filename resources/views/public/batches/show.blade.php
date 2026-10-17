<x-layouts.public :title="'Lô '.$batch->code">
    <header class="mb-4">
        <x-product-image :product="$batch->product" class="mb-4 aspect-[16/9] w-full rounded-xl" />
        <p class="text-sm text-stone-600">Thông tin lô hàng</p>
        <h1 class="text-2xl font-bold tracking-tight">{{ $batch->product->name }}</h1>
        <p class="mt-1 text-sm text-stone-600">Mã lô <span class="font-semibold text-stone-900">{{ $batch->code }}</span></p>
    </header>

    <x-flash />

    <section class="rounded-xl border border-stone-200 bg-white p-4" aria-label="Dữ kiện của lô">
        <dl class="divide-y divide-stone-100 text-sm">
            <div class="flex justify-between gap-4 py-2"><dt class="text-stone-600">Nông hộ</dt><dd class="text-right font-medium">{{ $batch->farmer->name }}</dd></div>
            @if ($batch->farmer->growing_area)
                <div class="flex justify-between gap-4 py-2"><dt class="shrink-0 text-stone-600">Khu vực trồng</dt><dd class="text-right">{{ $batch->farmer->growing_area }}</dd></div>
            @endif
            @if ($batch->farmer->farming_method)
                <div class="flex justify-between gap-4 py-2"><dt class="text-stone-600">Cách canh tác (theo nông hộ khai)</dt><dd class="text-right">{{ $batch->farmer->farming_method }}</dd></div>
            @endif
            <div class="flex justify-between gap-4 py-2"><dt class="text-stone-600">Thu hoạch</dt><dd class="text-right">{{ $batch->harvested_at->format('d/m/Y H:i') }}</dd></div>
            @if ($batch->processed_at)
                <div class="flex justify-between gap-4 py-2">
                    <dt class="shrink-0 text-stone-600">Sơ chế</dt>
                    <dd class="text-right">{{ $batch->processed_at->format('d/m/Y H:i') }}@if ($batch->processing_note)<span class="block text-stone-600">{{ $batch->processing_note }}</span>@endif</dd>
                </div>
            @endif
            @if ($batch->packed_at)
                <div class="flex justify-between gap-4 py-2"><dt class="text-stone-600">Đóng gói</dt><dd class="text-right">{{ $batch->packed_at->format('d/m/Y H:i') }}</dd></div>
            @endif
            @if ($batch->best_before)
                <div class="flex justify-between gap-4 py-2"><dt class="text-stone-600">Nên dùng trước</dt><dd class="text-right font-medium">{{ $batch->best_before->format('d/m/Y') }}</dd></div>
            @endif
            @if ($batch->storage_guide)
                <div class="py-2"><dt class="text-stone-600">Bảo quản</dt><dd class="mt-1">{{ $batch->storage_guide }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="mt-4 rounded-xl border border-stone-200 bg-white p-4 text-sm" aria-label="Chứng nhận">
        @if ($batch->farmer->hasValidCertificate())
            <p class="font-medium">Chứng nhận: {{ $batch->farmer->certificate_name }}</p>
            @if ($batch->farmer->certificate_expires_at)
                <p class="text-stone-600">Hiệu lực đến {{ $batch->farmer->certificate_expires_at->format('d/m/Y') }}</p>
            @endif
        @else
            <p class="text-stone-700">Chưa có chứng nhận được xác thực. Thông tin trên là dữ kiện do FARM2HOME ghi nhận khi nhận hàng.</p>
        @endif
    </section>

    <section class="mt-6" aria-labelledby="feedback-heading">
        <h2 id="feedback-heading" class="mb-2 text-lg font-semibold">Gửi phản hồi</h2>
        @if (Route::has('feedbacks.store'))
            <form method="POST" action="{{ route('feedbacks.store', [$batch->code, $batch->qr_key]) }}" class="space-y-4 rounded-xl border border-stone-200 bg-white p-4">
                @csrf
                @foreach (['rating' => 'Bạn đánh giá lô rau này thế nào?', 'freshness_rating' => 'Độ tươi (không bắt buộc)'] as $field => $legend)
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium text-stone-700">{{ $legend }}</legend>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach (range(1, 5) as $score)
                                <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white text-lg font-semibold has-checked:border-emerald-700 has-checked:bg-emerald-50">
                                    <input type="radio" name="{{ $field }}" value="{{ $score }}" class="sr-only" @checked((int) old($field) === $score)>
                                    {{ $score }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 flex justify-between text-xs text-stone-500"><span>1 = chưa tốt</span><span>5 = rất tốt</span></p>
                        @error($field)
                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                        @enderror
                    </fieldset>
                @endforeach

                <div>
                    <label for="comment" class="mb-1 block text-sm font-medium text-stone-700">Nhận xét (không bắt buộc)</label>
                    <textarea id="comment" name="comment" rows="3" class="block w-full rounded-lg border border-stone-300 px-3 py-2 text-base">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                    @enderror
                </div>

                <x-field name="phone" label="Số điện thoại đặt hàng (không bắt buộc, để chúng tôi liên hệ xử lý)" type="tel" inputmode="numeric" />

                <button class="min-h-12 w-full rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Gửi phản hồi</button>
            </form>
        @endif
    </section>
</x-layouts.public>
