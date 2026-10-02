<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;

/**
 * Danh mục tối thiểu cho feature test: 2 cụm giao, 3 loại rau, 2 nông hộ, 1 giỏ, 2 user.
 */
trait BuildsCatalog
{
    protected DeliveryZone $zone;

    protected DeliveryZone $pickupZone;

    protected Product $rm;

    protected Product $cn;

    protected Product $hl;

    protected Farmer $nh01;

    protected Farmer $nh02;

    protected Basket $basket;

    protected User $admin;

    protected User $operator;

    protected function buildCatalog(): void
    {
        $this->zone = DeliveryZone::factory()->create(['name' => 'Cụm 1 – Trung tâm', 'shipping_fee' => 10000, 'free_ship_from' => 150000, 'pickup_point' => null, 'is_active' => true]);
        $this->pickupZone = DeliveryZone::factory()->create(['name' => 'Điểm nhận tập trung', 'shipping_fee' => 0, 'free_ship_from' => null, 'pickup_point' => 'Cổng trường', 'is_active' => true]);

        $this->rm = $this->makeProduct('RM', 'Rau muống', 0.5, 12000, 14000, 2);
        $this->cn = $this->makeProduct('CN', 'Cải ngọt', 0.5, 15000, 18000, 3);
        $this->hl = $this->makeProduct('HL', 'Hành lá', 0.2, 8000, 30000, 5);

        $this->nh01 = Farmer::factory()->create([
            'code' => 'NH01', 'name' => 'Nguyễn Văn Tâm', 'phone' => '0911000001', 'zalo_phone' => '0911000001',
            'growing_area' => 'Xã Thân Cửu Nghĩa, Đồng Tháp', 'access_token' => str_repeat('a', 40), 'status' => 'active',
            'certificate_name' => null, 'certificate_file' => null, 'certificate_expires_at' => null,
        ]);
        $this->nh02 = Farmer::factory()->create([
            'code' => 'NH02', 'name' => 'Trần Thị Hồng', 'phone' => '0911000002', 'zalo_phone' => '0911000002',
            'growing_area' => 'Xã Thân Cửu Nghĩa, Đồng Tháp', 'access_token' => str_repeat('b', 40), 'status' => 'active',
            'certificate_name' => 'VietGAP (mẫu)', 'certificate_file' => 'certificates/nh02.pdf', 'certificate_expires_at' => '2027-06-30',
        ]);
        $this->nh01->products()->attach([$this->rm->id => ['typical_weekly_kg' => 40], $this->hl->id => ['typical_weekly_kg' => 10]]);
        $this->nh02->products()->attach([$this->rm->id => ['typical_weekly_kg' => 30], $this->cn->id => ['typical_weekly_kg' => 35], $this->hl->id => ['typical_weekly_kg' => 12]]);

        $this->basket = Basket::factory()->create(['name' => 'Giỏ Mini', 'slug' => 'gio-mini', 'household_min' => 1, 'household_max' => 2, 'price' => 45000, 'is_active' => true]);
        foreach ([$this->rm, $this->cn, $this->hl] as $product) {
            $this->basket->items()->create(['product_id' => $product->id, 'quantity' => 1, 'allow_substitute' => true]);
        }

        $this->admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => UserRole::Admin]);
        $this->operator = User::factory()->create(['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'password', 'role' => UserRole::Operator]);
    }

    private function makeProduct(string $code, string $name, float $packKg, int $price, int $costPerKg, int $shelfDays): Product
    {
        return Product::factory()->create([
            'code' => $code, 'name' => $name, 'slug' => str($name)->slug()->value(),
            'pack_weight_kg' => $packKg, 'price' => $price, 'cost_price_per_kg' => $costPerKg,
            'shelf_life_days' => $shelfDays, 'storage_guide' => 'Bảo quản ngăn mát 4–8°C.', 'is_active' => true,
        ]);
    }
}
