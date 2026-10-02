{{--
    Order type + basket / single product pickers, shared by the public and admin order forms.
    Must be rendered inside an element whose x-data is orderForm(...).
    Expects: $orderTypes, $baskets, $products, $maxQuantity
--}}
<fieldset>
    <legend class="mb-2 text-sm font-medium text-stone-700">Bạn muốn đặt gì?</legend>
    <div class="grid grid-cols-2 gap-2">
        @foreach ($orderTypes as $type)
            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border px-3 text-center font-medium"
                :class="type === '{{ $type->value }}' ? 'border-emerald-700 bg-emerald-50 text-emerald-900' : 'border-stone-300 bg-white'">
                <input type="radio" name="order_type" value="{{ $type->value }}" x-model="type" class="sr-only">
                {{ $type->label() }}
            </label>
        @endforeach
    </div>
    @error('order_type')
        <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
    @enderror
</fieldset>

@error('basket_id')
    <p class="text-sm text-rose-700" role="alert">{{ $message }}</p>
@enderror
@error('items')
    <p class="text-sm text-rose-700" role="alert">{{ $message }}</p>
@enderror
@if ($errors->has('items.*'))
    <p class="text-sm text-rose-700" role="alert">{{ $errors->first('items.*') }}</p>
@endif

<fieldset x-show="type === 'basket'" class="space-y-2">
    <legend class="mb-2 text-sm font-medium text-stone-700">Chọn giỏ</legend>
    @foreach ($baskets as $basket)
        <label class="flex cursor-pointer gap-3 rounded-xl border p-3"
            :class="basketId == {{ $basket->id }} ? 'border-emerald-700 bg-emerald-50' : 'border-stone-300 bg-white'">
            <input type="radio" name="basket_id" value="{{ $basket->id }}" x-model="basketId" :disabled="type !== 'basket'" class="mt-1 size-5 shrink-0">
            <span class="min-w-0 flex-1">
                <span class="flex items-baseline justify-between gap-2">
                    <span class="font-semibold">{{ $basket->name }}</span>
                    <span class="font-semibold text-emerald-800">@vnd($basket->price)</span>
                </span>
                <span class="mt-1 block text-sm text-stone-600">
                    {{ $basket->items->map(fn ($item) => $item->quantity.' × '.$item->product->name)->join(', ') }}
                </span>
            </span>
        </label>
    @endforeach
</fieldset>

<fieldset x-show="type === 'single'" x-cloak class="space-y-2">
    <legend class="mb-2 text-sm font-medium text-stone-700">Chọn rau (số gói)</legend>
    @foreach ($products as $index => $product)
        <div class="flex items-center gap-3 rounded-xl border border-stone-300 bg-white p-3">
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $product->name }}</p>
                <p class="text-sm text-stone-600">@kg($product->pack_weight_kg)/gói · @vnd($product->price)</p>
            </div>
            <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}" :disabled="type !== 'single'">
            <div class="flex items-center gap-1">
                <button type="button" class="size-11 rounded-lg border border-stone-300 text-xl leading-none" aria-label="Bớt {{ $product->name }}"
                    @click="qty[{{ $product->id }}] = Math.max(0, (qty[{{ $product->id }}] || 0) - 1)">−</button>
                <input type="number" inputmode="numeric" min="0" max="{{ $maxQuantity }}" name="items[{{ $index }}][quantity]"
                    x-model.number="qty[{{ $product->id }}]" :disabled="type !== 'single'" aria-label="Số gói {{ $product->name }}"
                    class="h-11 w-14 rounded-lg border border-stone-300 text-center text-base">
                <button type="button" class="size-11 rounded-lg border border-stone-300 text-xl leading-none" aria-label="Thêm {{ $product->name }}"
                    @click="qty[{{ $product->id }}] = Math.min({{ $maxQuantity }}, (qty[{{ $product->id }}] || 0) + 1)">+</button>
            </div>
        </div>
    @endforeach
</fieldset>
