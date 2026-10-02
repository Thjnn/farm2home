@props(['name', 'label', 'type' => 'text', 'value' => null])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->class(['block w-full min-h-11 rounded-lg border bg-white px-3 text-base', 'border-rose-500' => $errors->has($name), 'border-stone-300' => ! $errors->has($name)]) }}>
    @error($name)
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
</div>
