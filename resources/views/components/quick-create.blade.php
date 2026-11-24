@php
    // Mục lấy từ config/admin_quick_create.php, lọc theo vai trò; nhóm rỗng thì ẩn.
    $role = auth()->user()->role->value;
    $groups = collect(config('admin_quick_create'))
        ->map(fn ($group) => [...$group, 'items' => collect($group['items'])->filter(fn ($item) => in_array($role, $item['roles'], true) && Route::has($item['route']))->values()])
        ->filter(fn ($group) => $group['items']->isNotEmpty())
        ->values();
    $tones = [
        'green' => 'bg-emerald-50 text-emerald-700',
        'blue' => 'bg-sky-50 text-sky-700',
        'rose' => 'bg-rose-50 text-rose-700',
        'amber' => 'bg-amber-50 text-amber-700',
    ];
@endphp
@if ($groups->isNotEmpty())
    <div x-data="quickCreate" class="relative" @keydown.window="shortcut($event)" @click.outside="close(false)">
        <button type="button" x-ref="button" @click="toggle()" aria-haspopup="menu" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="quick-create-menu"
            title="Tạo mới (phím N)" aria-keyshortcuts="N"
            class="inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-lg bg-emerald-700 px-3 font-semibold text-white shadow-sm hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 active:scale-[0.97] motion-reduce:active:scale-100 md:px-4">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            <span class="sr-only md:not-sr-only">Tạo mới</span>
            <svg class="hidden size-4 transition-transform duration-150 motion-reduce:transition-none md:block" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        {{-- Điện thoại: lớp phủ phía sau bottom sheet. --}}
        <div x-show="open" x-cloak x-transition.opacity.duration.150ms @click="close()" class="fixed inset-0 z-40 bg-stone-900/40 md:hidden" aria-hidden="true"></div>

        <div id="quick-create-menu" x-show="open" x-cloak role="menu" aria-label="Tạo mới" @keydown="navigate($event)"
            x-transition:enter="transition duration-150 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 max-md:translate-y-full md:-translate-y-1.5"
            x-transition:leave="transition duration-100 ease-in motion-reduce:transition-none" x-transition:leave-end="opacity-0 max-md:translate-y-full md:-translate-y-1.5"
            class="fixed inset-x-0 bottom-0 z-50 max-h-[80dvh] overflow-y-auto rounded-t-2xl bg-white px-3 pb-[max(1rem,env(safe-area-inset-bottom))] pt-2 shadow-2xl md:absolute md:inset-x-auto md:bottom-auto md:right-0 md:top-full md:mt-2 md:max-h-none md:w-[560px] md:rounded-xl md:border md:border-stone-200 md:p-3 md:shadow-lg">
            <div class="mx-auto mb-2 h-1.5 w-10 rounded-full bg-stone-300 md:hidden" aria-hidden="true"></div>
            @foreach ($groups as $group)
                <div role="group" aria-labelledby="quick-create-group-{{ $loop->index }}" @class(['mt-2 border-t border-stone-100 pt-2' => ! $loop->first])>
                    <p id="quick-create-group-{{ $loop->index }}" class="px-2 pb-1 text-xs font-medium uppercase tracking-wide text-stone-500">{{ $group['group'] }}</p>
                    <div class="grid gap-0.5 md:grid-cols-2">
                        @foreach ($group['items'] as $item)
                            <a href="{{ route($item['route']) }}{{ isset($item['anchor']) ? '#'.$item['anchor'] : '' }}" role="menuitem" tabindex="-1" data-quick-item @click="close(false)"
                                class="flex min-h-14 items-center gap-3 rounded-lg px-2 py-2 hover:bg-stone-50 focus:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $tones[$item['color']] ?? $tones['green'] }}">
                                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-stone-900">{{ $item['label'] }}</span>
                                    <span class="block truncate text-xs text-stone-600">{{ $item['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <p class="mt-2 hidden border-t border-stone-100 px-2 pt-2 text-xs text-stone-500 md:block">Mẹo: nhấn <kbd class="rounded border border-stone-300 bg-stone-50 px-1.5 font-sans">N</kbd> ở bất kỳ trang admin nào để mở menu này.</p>
        </div>
    </div>
@endif
