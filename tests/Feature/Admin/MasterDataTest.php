<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Basket;
use App\Models\DeliveryZone;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-02 10:00:00');
        $this->buildCatalog();
    }

    public function test_operator_cannot_manage_master_data(): void
    {
        $this->actingAs($this->operator);

        foreach (['products', 'baskets', 'farmers', 'zones', 'users'] as $resource) {
            $this->get("/admin/{$resource}")->assertForbidden();
        }
        $this->post('/admin/products', [])->assertForbidden();
    }

    public function test_admin_sees_every_index(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/products')->assertOk()->assertSee('Rau muống')->assertSee('12.000đ');
        $this->get('/admin/baskets')->assertOk()->assertSee('Giỏ Mini');
        $this->get('/admin/farmers')->assertOk()->assertSee('Nguyễn Văn Tâm')->assertSee('/nong-ho/'.$this->nh01->access_token);
        $this->get('/admin/zones')->assertOk()->assertSee('Cụm 1 – Trung tâm');
        $this->get('/admin/users')->assertOk()->assertSee('operator@example.test');
    }

    public function test_create_and_edit_forms_render(): void
    {
        $this->actingAs($this->admin);

        foreach (['products', 'baskets', 'farmers', 'zones', 'users'] as $resource) {
            $this->get("/admin/{$resource}/create")->assertOk();
        }
        $this->get('/admin/products/'.$this->rm->id.'/edit')->assertOk()->assertSee('value="RM"', false);
        $this->get('/admin/baskets/'.$this->basket->id.'/edit')->assertOk()->assertSee('Giỏ Mini');
        $this->get('/admin/farmers/'.$this->nh02->id.'/edit')->assertOk()->assertSee('VietGAP (mẫu)');
        $this->get('/admin/zones/'.$this->zone->id.'/edit')->assertOk()->assertSee('value="10000"', false);
        $this->get('/admin/users/'.$this->operator->id.'/edit')->assertOk()->assertSee('operator@example.test');
    }

    public function test_admin_creates_and_updates_a_product(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/products', [
            'code' => 'mt', 'name' => 'Mồng tơi', 'pack_weight_kg' => '0.5', 'price' => 12000,
            'cost_price_per_kg' => 14000, 'shelf_life_days' => 2, 'storage_guide' => 'Ngăn mát', 'is_active' => '1',
        ])->assertRedirect('/admin/products');

        $product = Product::where('code', 'MT')->firstOrFail();
        $this->assertSame(['mong-toi', '0.500', true], [$product->slug, $product->pack_weight_kg, $product->is_active]);

        $this->put('/admin/products/'.$product->id, [
            'code' => 'MT', 'name' => 'Mồng tơi', 'pack_weight_kg' => '0.5', 'price' => 13000,
            'cost_price_per_kg' => 14000, 'shelf_life_days' => 2,
        ])->assertRedirect('/admin/products');

        $this->assertSame([13000, false], [$product->refresh()->price, $product->is_active]);
    }

    public function test_product_code_must_be_unique(): void
    {
        $this->actingAs($this->admin)->post('/admin/products', [
            'code' => 'RM', 'name' => 'Rau muống 2', 'pack_weight_kg' => '0.5', 'price' => 1, 'cost_price_per_kg' => 1, 'shelf_life_days' => 2,
        ])->assertSessionHasErrors('code');
    }

    public function test_admin_creates_a_basket_with_items(): void
    {
        $this->actingAs($this->admin)->post('/admin/baskets', [
            'name' => 'Giỏ Gia Đình', 'household_min' => 3, 'household_max' => 5, 'price' => 115000, 'is_active' => '1',
            'items' => [
                ['product_id' => $this->rm->id, 'quantity' => 2, 'allow_substitute' => '1'],
                ['product_id' => $this->cn->id, 'quantity' => 0],
                ['product_id' => $this->hl->id, 'quantity' => 1],
            ],
        ])->assertRedirect('/admin/baskets');

        $basket = Basket::where('slug', 'gio-gia-dinh')->firstOrFail();
        $this->assertSame([$this->rm->id => 2, $this->hl->id => 1], $basket->items->pluck('quantity', 'product_id')->all());
    }

    public function test_basket_needs_at_least_one_item_and_a_valid_household_range(): void
    {
        $this->actingAs($this->admin)->post('/admin/baskets', [
            'name' => 'Giỏ rỗng', 'household_min' => 5, 'household_max' => 3, 'price' => 1,
            'items' => [['product_id' => $this->rm->id, 'quantity' => 0]],
        ])->assertSessionHasErrors(['household_max']);

        $this->actingAs($this->admin)->post('/admin/baskets', [
            'name' => 'Giỏ rỗng', 'household_min' => 1, 'household_max' => 3, 'price' => 1,
            'items' => [['product_id' => $this->rm->id, 'quantity' => 0]],
        ])->assertSessionHasErrors(['items']);

        $this->assertSame(1, Basket::count());
    }

    public function test_admin_creates_a_farmer_with_token_products_and_certificate(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/admin/farmers', [
            'code' => 'NH03', 'name' => 'Lê Văn Phúc', 'phone' => '0911000003', 'status' => 'active',
            'growing_area' => 'Gò Công, Đồng Tháp',
            'certificate_name' => 'VietGAP', 'certificate_expires_at' => '2027-12-31',
            'certificate' => UploadedFile::fake()->create('vietgap.pdf', 200, 'application/pdf'),
            'products' => [$this->rm->id => '30', $this->cn->id => ''],
        ])->assertRedirect('/admin/farmers');

        $farmer = Farmer::where('code', 'NH03')->firstOrFail();
        $this->assertSame(40, strlen($farmer->access_token));
        $this->assertSame('0911000003', $farmer->zalo_phone);
        $this->assertSame([$this->rm->id], $farmer->products->pluck('id')->all());
        $this->assertTrue($farmer->hasValidCertificate());
        Storage::disk('public')->assertExists($farmer->certificate_file);
    }

    public function test_regenerating_the_token_invalidates_the_old_link(): void
    {
        $old = $this->nh01->access_token;

        $this->actingAs($this->admin)->post('/admin/farmers/'.$this->nh01->id.'/token')->assertRedirect();

        $this->assertNotSame($old, $this->nh01->refresh()->access_token);
        auth()->logout();
        $this->get('/nong-ho/'.$old)->assertNotFound();
        $this->get('/nong-ho/'.$this->nh01->access_token)->assertOk();
    }

    public function test_admin_manages_zones(): void
    {
        $this->actingAs($this->admin)->post('/admin/zones', [
            'name' => 'Cụm 2', 'shipping_fee' => 15000, 'free_ship_from' => 200000, 'is_active' => '1',
        ])->assertRedirect('/admin/zones');

        $zone = DeliveryZone::where('name', 'Cụm 2')->firstOrFail();
        $this->assertSame(15000, $zone->shipping_fee);

        $this->put('/admin/zones/'.$zone->id, ['name' => 'Cụm 2', 'shipping_fee' => 0, 'pickup_point' => 'Cổng chợ'])->assertRedirect('/admin/zones');
        $this->assertSame([0, null, 'Cổng chợ', false], [$zone->refresh()->shipping_fee, $zone->free_ship_from, $zone->pickup_point, $zone->is_active]);
    }

    public function test_admin_creates_a_user_and_cannot_demote_themselves(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Vận hành 2', 'email' => 'op2@example.test', 'role' => 'operator', 'password' => 'mat-khau-dai',
        ])->assertRedirect('/admin/users');

        $created = User::where('email', 'op2@example.test')->firstOrFail();
        $this->assertSame(UserRole::Operator, $created->role);
        $this->assertTrue(password_verify('mat-khau-dai', $created->password));

        $this->from('/admin/users/'.$this->admin->id.'/edit')
            ->put('/admin/users/'.$this->admin->id, ['name' => 'Admin', 'email' => 'admin@example.test', 'role' => 'operator'])
            ->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $this->admin->refresh()->role);
    }

    public function test_short_password_is_rejected_and_blank_password_keeps_the_old_one(): void
    {
        $this->actingAs($this->admin);

        $this->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'role' => 'operator', 'password' => '123'])
            ->assertSessionHasErrors('password');

        $hash = $this->operator->password;
        $this->put('/admin/users/'.$this->operator->id, ['name' => 'Operator mới', 'email' => 'operator@example.test', 'role' => 'operator', 'password' => ''])
            ->assertRedirect('/admin/users');

        $this->assertSame([$hash, 'Operator mới'], [$this->operator->refresh()->password, $this->operator->name]);
    }
}
