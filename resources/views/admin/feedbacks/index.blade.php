<x-layouts.admin title="Phản hồi của khách">
    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Lọc theo trạng thái">
        <a href="{{ route('admin.feedbacks.index') }}" @class(['rounded-full border px-3 py-1.5', 'border-emerald-700 bg-emerald-50 font-semibold text-emerald-900' => $status === null, 'border-stone-300 bg-white' => $status !== null])>Tất cả</a>
        @foreach ($statuses as $option)
            <a href="{{ route('admin.feedbacks.index', ['status' => $option->value]) }}" @class(['rounded-full border px-3 py-1.5', 'border-emerald-700 bg-emerald-50 font-semibold text-emerald-900' => $status === $option->value, 'border-stone-300 bg-white' => $status !== $option->value])>{{ $option->label() }}</a>
        @endforeach
    </nav>

    <div class="space-y-3">
        @forelse ($feedbacks as $feedback)
            <article class="rounded-xl border border-stone-200 bg-white p-4 text-sm">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="text-base font-semibold">{{ $feedback->rating }}/5</span>
                    @if ($feedback->freshness_rating)
                        <span class="text-stone-600">Độ tươi {{ $feedback->freshness_rating }}/5</span>
                    @endif
                    <x-status-badge :status="$feedback->status" />
                    <span class="ml-auto text-stone-500">{{ $feedback->created_at->format('H:i d/m/Y') }}</span>
                </div>
                @if ($feedback->comment)
                    <p class="mt-2">{{ $feedback->comment }}</p>
                @endif
                <p class="mt-2 text-stone-600">
                    Lô <a href="{{ route('admin.batches.show', $feedback->batch) }}" class="text-emerald-800 underline">{{ $feedback->batch->code }}</a>
                    · {{ $feedback->batch->product->name }} · {{ $feedback->batch->farmer->name }}
                    @if ($feedback->customer)
                        · Khách: {{ $feedback->customer->name }} ({{ $feedback->customer->phone }})
                    @endif
                    @if ($feedback->order)
                        · Đơn <a href="{{ route('admin.orders.show', $feedback->order) }}" class="text-emerald-800 underline">{{ $feedback->order->code }}</a>
                    @endif
                </p>

                <form method="POST" action="{{ route('admin.feedbacks.update', $feedback) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-stone-100 pt-3">
                    @csrf
                    @method('PATCH')
                    <div class="min-w-60 flex-1">
                        <label for="response-{{ $feedback->id }}" class="mb-1 block text-stone-700">Cách xử lý</label>
                        <textarea id="response-{{ $feedback->id }}" name="response" rows="2" class="block w-full rounded-lg border border-stone-300 px-3 py-2">{{ $feedback->response }}</textarea>
                    </div>
                    <div>
                        <label for="status-{{ $feedback->id }}" class="mb-1 block text-stone-700">Trạng thái</label>
                        <select id="status-{{ $feedback->id }}" name="status" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3">
                            @foreach ($statuses as $option)
                                <option value="{{ $option->value }}" @selected($feedback->status === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="min-h-11 rounded-lg bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">Lưu</button>
                </form>
            </article>
        @empty
            <p class="rounded-lg bg-stone-100 px-4 py-3 text-stone-700">Chưa có phản hồi nào.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $feedbacks->links() }}</div>
</x-layouts.admin>
