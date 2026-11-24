<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogPageRequest extends FormRequest
{
    public const MAX_LIMIT = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'q' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', Rule::enum(ProductCategory::class)],
            // Trang khách chỉ xem giỏ đang bán; active=true có thể ghi cho rõ nghĩa.
            'active' => ['sometimes', Rule::in(['true', '1'])],
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function limit(int $default): int
    {
        return (int) $this->validated('limit', $default);
    }

    public function category(): ?ProductCategory
    {
        return ProductCategory::tryFrom((string) $this->validated('category'));
    }
}
