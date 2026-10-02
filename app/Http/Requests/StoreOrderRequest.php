<?php

namespace App\Http\Requests;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Models\DeliveryZone;
use App\Support\OrderWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        $now = CarbonImmutable::now();
        $earliest = OrderWindow::earliest($now, config('farm2home.order.cutoff_hour'))->toDateString();
        $latest = OrderWindow::latest($now, config('farm2home.order.max_days_ahead'))->toDateString();

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^0\d{9}$/'],
            'delivery_zone_id' => ['required', Rule::exists('delivery_zones', 'id')->where('is_active', true)],
            'address' => [Rule::requiredIf(fn () => ! $this->zoneIsPickup()), 'nullable', 'string', 'max:255'],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$earliest, 'before_or_equal:'.$latest],
            'time_slot' => ['required', Rule::in(config('farm2home.order.time_slots'))],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'basket_id' => ['required_if:order_type,basket', 'nullable', Rule::exists('baskets', 'id')->where('is_active', true)],
            'items' => ['required_if:order_type,single', 'nullable', 'array', 'max:20'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:20'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'data_consent' => ['accepted'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['items.*.quantity' => 'số gói', 'items.*.product_id' => 'loại rau'];
    }

    private function zoneIsPickup(): bool
    {
        return (bool) DeliveryZone::find($this->input('delivery_zone_id'))?->isPickup();
    }
}
