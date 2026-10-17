<x-layouts.admin title="Nông hộ">
    <p class="mb-4"><a href="{{ route('admin.farmers.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Thêm nông hộ</a></p>

    <div class="space-y-3">
        @foreach ($farmers as $farmer)
            <article class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h2 class="text-base font-semibold">{{ $farmer->code }} · {{ $farmer->name }}</h2>
                    <x-status-badge :status="$farmer->status" />
                    <a href="{{ route('admin.farmers.edit', $farmer) }}" class="ml-auto text-emerald-800 underline">Sửa</a>
                </div>
                <p class="mt-1 text-stone-700">
                    {{ $farmer->phone }} · {{ $farmer->growing_area ?? 'Chưa ghi khu vực trồng' }} ·
                    Chứng nhận:
                    @if ($farmer->hasValidCertificate())
                        Có{{ $farmer->certificate_expires_at ? ', đến '.$farmer->certificate_expires_at->format('d/m/Y') : '' }}
                        @if ($farmer->certificateDaysLeft() !== null && $farmer->certificateDaysLeft() <= config('farm2home.certificate_warning_days'))
                            <span class="font-semibold text-amber-800">(còn {{ $farmer->certificateDaysLeft() }} ngày, nhắc gia hạn)</span>
                        @endif
                    @elseif ($farmer->certificate_file)
                        Hết hạn
                    @else
                        Không
                    @endif
                </p>
                <p class="mt-1 text-stone-700">Trồng: {{ $farmer->products->pluck('name')->join(', ') ?: 'chưa gắn loại rau' }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="text-stone-600">Link báo sản lượng:</span>
                    <code class="break-all rounded bg-stone-100 px-1.5 py-0.5">{{ route('farmer.show', $farmer->access_token) }}</code>
                    <form method="POST" action="{{ route('admin.farmers.token', $farmer) }}">
                        @csrf
                        <button class="rounded-md border border-stone-300 px-2 py-1 hover:bg-stone-100">Cấp link mới</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.admin>
