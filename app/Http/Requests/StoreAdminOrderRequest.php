<?php

namespace App\Http\Requests;

use App\Enums\OrderChannel;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\DeliveryZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Operator nhập hộ: không áp giờ chốt, chỉ chặn ngày đã qua.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'address' => [Rule::requiredIf(fn () => ! DeliveryZone::find($this->input('delivery_zone_id'))?->isPickup()), 'nullable', 'string', 'max:255'],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.today()->toDateString()],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'basket_id' => ['required_if:order_type,basket', 'nullable', Rule::exists('baskets', 'id')->where('is_active', true)],
            'items' => ['required_if:order_type,single', 'nullable', 'array', 'max:20'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:50'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'channel' => ['required', Rule::enum(OrderChannel::class)],
            'data_consent' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
