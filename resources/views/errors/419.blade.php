<x-layouts.public title="Trang đã hết hạn">
    <section class="rounded-xl border border-stone-200 bg-white p-5">
        <h1 class="text-xl font-semibold">Trang đã hết hạn</h1>
        <p class="mt-1 text-sm text-stone-600">Trang mở đã lâu nên chưa gửi được. Tải lại trang rồi thử lại nhé.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <a href="{{ url()->previous() }}" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 font-medium text-white hover:bg-emerald-800">Tải lại trang</a>
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 font-medium hover:bg-stone-100">Về trang chủ</a>
        </div>
    </section>
</x-layouts.public>
