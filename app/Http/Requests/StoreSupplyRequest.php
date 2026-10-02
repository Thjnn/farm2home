<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'supply' => ['required', 'array'],
            'supply.*' => ['array'],
            'supply.*.*' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['supply.*.*' => 'số kg'];
    }
}
