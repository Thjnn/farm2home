@php
    use App\Enums\FeedbackStatus;

    $chip = fn (bool $active) => $active
        ? 'border-emerald-700 bg-emerald-700 text-white'
        : 'border-stone-300 bg-white text-stone-700 hover:bg-stone-100';
@endphp
<x-layouts.admin title="Phản hồi của khách">
    <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Lọc theo trạng thái">
        <a href="{{ route('admin.feedbacks.index') }}" @if ($status === null) aria-current="page" @endif
            class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 font-medium {{ $chip($status === null) }}">
            Tất cả <span class="tabular-nums opacity-80">{{ $statusCounts->sum() }}</span>
        </a>
        @foreach ($statuses as $option)
            <a href="{{ route('admin.feedbacks.index', ['status' => $option->value]) }}" @if ($status === $option->value) aria-current="page" @endif
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-4 font-medium {{ $chip($status === $option->value) }}">
                {{ $option->label() }} <span class="tabular-nums opacity-80">{{ $statusCounts[$option->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="space-y-3">
        @forelse ($feedbacks as $feedback)
            @php($isNew = $feedback->status === FeedbackStatus::New)
            {{-- Phản hồi mới mở sẵn form xử lý; đã xử lý thì gọn lại, bấm "Sửa" để mở. --}}
            <article x-data="{ open: @js($isNew) }"
                @class([
                    'rounded-xl border bg-white p-4 text-sm',
                    'border-rose-200' => $feedback->rating <= 2,
                    'border-stone-200' => $feedback->rating > 2,
                ])>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-base font-semibold', 'bg-rose-50 text-rose-800' => $feedback->rating <= 2, 'bg-amber-50 text-amber-900' => $feedback->rating > 2])>
                        <span aria-hidden="true" class="text-amber-500">★</span>{{ $feedback->rating }}/5
                    </span>
                    @if ($feedback->freshness_rating)
                        <span class="text-stone-600">Độ tươi {{ $feedback->freshness_rating }}/5</span>
                    @endif
                    <x-status-badge :status="$feedback->status" />
                    <span class="ml-auto text-stone-500">{{ $feedback->created_at->format('H:i d/m/Y') }}</span>
                </div>
                @if ($feedback->comment)
                    <p class="mt-2 text-base">“{{ $feedback->comment }}”</p>
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

                @if (! $isNew && $feedback->response)
                    <p x-show="!open" class="mt-2 rounded-lg bg-stone-50 px-3 py-2 text-stone-700"><span class="font-medium">Đã xử lý:</span> {{ $feedback->response }}</p>
                @endif
                <button type="button" x-show="!open" @if ($isNew) x-cloak @endif @click="open = true" :aria-expanded="open.toString()" aria-controls="feedback-form-{{ $feedback->id }}"
                    class="mt-2 inline-flex min-h-11 items-center rounded-lg px-2 font-medium text-emerald-800 hover:bg-emerald-50">
                    {{ $feedback->response ? 'Sửa cách xử lý' : 'Xử lý' }}
                </button>

                <form id="feedback-form-{{ $feedback->id }}" x-show="open" @unless ($isNew) x-cloak @endunless method="POST" action="{{ route('admin.feedbacks.update', $feedback) }}"
                    class="mt-3 flex flex-wrap items-end gap-2 border-t border-stone-100 pt-3">
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
                    @unless ($isNew)
                        <button type="button" @click="open = false" class="min-h-11 rounded-lg px-3 text-stone-600 hover:bg-stone-100">Đóng</button>
                    @endunless
                </form>
            </article>
        @empty
            <p class="rounded-lg bg-stone-100 px-4 py-3 text-stone-700">{{ $status ? 'Không có phản hồi nào ở trạng thái này.' : 'Chưa có phản hồi nào.' }}</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $feedbacks->links() }}</div>
</x-layouts.admin>
