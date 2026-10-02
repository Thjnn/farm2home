<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', ['products' => Product::orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(['is_active' => true, 'pack_weight_kg' => 0.5, 'shelf_life_days' => 3])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request, new Product));

        return redirect()->route('admin.products.index')->with('status', 'Đã thêm sản phẩm.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', ['product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));

        return redirect()->route('admin.products.index')->with('status', 'Đã cập nhật sản phẩm.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Product $product): array
    {
        $request->merge(['code' => Str::upper((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:150'],
            'pack_weight_kg' => ['required', 'numeric', 'min:0.05', 'max:10'],
            'price' => ['required', 'integer', 'min:0'],
            'cost_price_per_kg' => ['required', 'integer', 'min:0'],
            'shelf_life_days' => ['required', 'integer', 'min:1', 'max:30'],
            'storage_guide' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['slug'] = $this->uniqueSlug($data['name'], $product);

        return $data;
    }

    private function uniqueSlug(string $name, Product $product): string
    {
        $slug = Str::slug($name);
        $taken = Product::where('slug', $slug)->where('id', '!=', $product->id ?? 0)->exists();

        return $taken ? $slug.'-'.Str::lower(Str::random(4)) : $slug;
    }
}
