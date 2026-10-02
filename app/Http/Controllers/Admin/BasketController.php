<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Basket;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BasketController extends Controller
{
    public function index(): View
    {
        return view('admin.baskets.index', ['baskets' => Basket::with('items.product')->orderBy('price')->get()]);
    }

    public function create(): View
    {
        return $this->form(new Basket(['is_active' => true, 'household_min' => 1, 'household_max' => 2]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Basket);

        return redirect()->route('admin.baskets.index')->with('status', 'Đã thêm giỏ rau.');
    }

    public function edit(Basket $basket): View
    {
        return $this->form($basket->load('items'));
    }

    public function update(Request $request, Basket $basket): RedirectResponse
    {
        $this->save($request, $basket);

        return redirect()->route('admin.baskets.index')->with('status', 'Đã cập nhật giỏ rau.');
    }

    private function form(Basket $basket): View
    {
        return view('admin.baskets.form', [
            'basket' => $basket,
            'products' => Product::where('is_active', true)->orderBy('id')->get(),
            'lines' => $basket->exists ? $basket->items->keyBy('product_id') : collect(),
        ]);
    }

    /**
     * Save the basket and replace its lines in one transaction. Lines with quantity 0 are dropped.
     *
     * @throws ValidationException when no line has a quantity
     */
    private function save(Request $request, Basket $basket): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'household_min' => ['required', 'integer', 'min:1', 'max:20'],
            'household_max' => ['required', 'integer', 'min:1', 'max:20', 'gte:household_min'],
            'price' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:20'],
            'items.*.allow_substitute' => ['nullable', 'boolean'],
        ]);

        $lines = collect($data['items'])->filter(fn (array $line) => (int) $line['quantity'] > 0)->unique('product_id');

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Giỏ phải có ít nhất một loại rau.']);
        }

        DB::transaction(function () use ($basket, $data, $lines, $request) {
            $basket->fill([
                'name' => $data['name'],
                'slug' => $basket->exists ? $basket->slug : $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'household_min' => $data['household_min'],
                'household_max' => $data['household_max'],
                'price' => $data['price'],
                'is_active' => $request->boolean('is_active'),
            ])->save();

            $basket->items()->delete();

            foreach ($lines as $line) {
                $basket->items()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'allow_substitute' => (bool) ($line['allow_substitute'] ?? true),
                ]);
            }
        });
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);

        return Basket::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
    }
}
