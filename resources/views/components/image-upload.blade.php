@props(['name', 'id' => null, 'accept' => 'image/*', 'multiple' => false, 'current' => null, 'hint' => null, 'label' => null])
@php
    $key = str_replace('[]', '', $name);
    $id ??= $key;
    $current = array_values(array_filter((array) $current));
    $hasError = $errors->has($key) || $errors->has("$key.*");
    $noun = str_starts_with($accept, 'image/') ? 'ảnh' : 'file';
    $button = 'inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-medium shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600';
@endphp
<div {{ $attributes->class('relative') }}
    x-data="imageUpload({ multiple: @js((bool) $multiple), current: @js($current), accept: @js($accept) })"
    @dragover.prevent="dragging = true" @dragleave="leave($event)" @drop.prevent="drop($event)">
    @if ($label)
        <p class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}</p>
    @endif

    @if ($multiple)
        <div x-show="hasContent" x-cloak class="grid grid-cols-3 gap-2 rounded-xl p-1 transition-colors motion-reduce:transition-none sm:grid-cols-4"
            :class="dragging ? 'bg-emerald-50 ring-2 ring-emerald-500' : ''">
            <template x-for="(entry, index) in files" :key="entry.url ?? entry.name">
                <div class="relative aspect-square overflow-hidden rounded-lg bg-stone-100">
                    <img :src="entry.url" :alt="entry.name" :title="entry.name" class="size-full object-cover">
                    <button type="button" @click="remove(index)" :aria-label="`Bỏ ảnh ${entry.name}`"
                        class="absolute right-1 top-1 flex size-8 items-center justify-center rounded-full bg-white/90 text-stone-700 shadow hover:bg-white hover:text-rose-700 focus-visible:outline-2 focus-visible:outline-emerald-600">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
            </template>
            <template x-for="url in (files.length ? [] : current)" :key="url">
                <img :src="url" alt="Ảnh đang lưu" class="aspect-square w-full rounded-lg bg-stone-100 object-cover">
            </template>
            <button type="button" @click="browse()"
                class="flex aspect-square flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed border-stone-300 bg-stone-50 text-sm text-stone-600 transition-colors hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 motion-reduce:transition-none">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 5h6"/><path d="M19 2v6"/><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/><circle cx="9" cy="9" r="2"/></svg>
                Thêm ảnh
            </button>
        </div>
    @else
        <div x-show="showsImage" x-cloak class="group relative size-40 overflow-hidden rounded-xl border bg-stone-100 transition-colors motion-reduce:transition-none"
            :class="dragging ? 'border-emerald-500 ring-2 ring-emerald-500' : 'border-stone-200'">
            <img :src="imageSrc" alt="Ảnh xem trước" class="size-full object-cover">
            <div class="absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-linear-to-t from-black/60 to-transparent p-2 transition-opacity motion-reduce:transition-none sm:inset-0 sm:items-center sm:bg-black/40 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                <button type="button" @click="browse()" class="{{ $button }} bg-white text-stone-800 hover:bg-stone-100">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                    Đổi ảnh
                </button>
                <button type="button" x-show="preview" @click="clear()" class="{{ $button }} bg-rose-600 text-white hover:bg-rose-700" aria-label="Xoá ảnh vừa chọn">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                    Xoá
                </button>
            </div>
        </div>
    @endif

    <label for="{{ $id }}"
        class="flex min-h-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors motion-reduce:transition-none has-focus-visible:ring-2 has-focus-visible:ring-emerald-600 has-focus-visible:ring-offset-2"
        :class="[dragging ? 'border-emerald-500 bg-emerald-50' : '{{ $hasError ? 'border-rose-400 bg-rose-50' : 'border-stone-300 bg-stone-50' }} hover:border-stone-400 hover:bg-stone-100', dropHidden ? 'sr-only' : '']">
        <span class="mb-1 flex size-11 items-center justify-center rounded-full bg-white text-stone-500 shadow-sm ring-1 ring-stone-200"
            :class="dragging ? 'text-emerald-700 ring-emerald-300' : ''">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 5h6"/><path d="M19 2v6"/><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7.5"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/><circle cx="9" cy="9" r="2"/></svg>
        </span>
        <span class="text-sm font-medium text-stone-800" x-text="dragging ? 'Thả {{ $noun }} vào đây' : 'Bấm để chọn'">Bấm để chọn</span>
        <span class="text-sm text-stone-600">hoặc kéo thả {{ $noun }} vào đây</span>
        <input x-ref="input" id="{{ $id }}" name="{{ $name }}" type="file" accept="{{ $accept }}" @if ($multiple) multiple @endif
            class="sr-only" @change="pick($event)" :tabindex="dropHidden ? -1 : null"
            @if ($label) aria-label="{{ $label }}" @endif @if ($hint) aria-describedby="{{ $id }}-hint" @endif>
    </label>

    @unless ($multiple)
        <div x-show="preview" x-cloak class="mt-2 flex min-h-11 max-w-md items-center gap-2 rounded-lg border border-stone-200 bg-white pl-3 pr-1 text-sm">
            <svg class="size-4 shrink-0 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
            <span class="min-w-0 flex-1 truncate text-stone-800" x-text="preview?.name"></span>
            <button type="button" @click="clear()" aria-label="Bỏ file vừa chọn"
                class="flex size-11 shrink-0 items-center justify-center rounded-lg text-stone-500 hover:bg-stone-100 hover:text-rose-700 focus-visible:outline-2 focus-visible:outline-emerald-600">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
    @endunless

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1 text-xs text-stone-500">{{ $hint }}</p>
    @endif
    <p x-show="error" x-text="error" x-cloak role="alert" class="mt-1 text-sm text-rose-700"></p>
    @error($key)
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
    @error("$key.*")
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
</div>
