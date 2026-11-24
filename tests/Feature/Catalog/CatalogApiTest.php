<?php

namespace Tests\Feature\Catalog;

use App\Models\Basket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_paged_with_totals_and_no_overlap_between_pages(): void
    {
        Product::factory()->count(14)->create();
        Product::factory()->create(['is_active' => false]);

        $first = $this->getJson('/api/products?page=1&limit=6')->assertOk()
            ->assertJson(['page' => 1, 'limit' => 6, 'total' => 14, 'totalPages' => 3])
            ->assertJsonCount(6, 'items')
            ->assertJsonStructure(['items' => [['id', 'name', 'price', 'unit', 'category', 'imageUrl']]]);
        $second = $this->getJson('/api/products?page=2&limit=6')->assertJsonCount(6, 'items');
        $third = $this->getJson('/api/products?page=3&limit=6')->assertJsonCount(2, 'items');

        $ids = collect([$first, $second, $third])->flatMap(fn ($response) => $response->json('items.*.id'));
        $this->assertCount(14, $ids->unique());
    }

    public function test_best_sellers_come_first_then_by_id(): void
    {
        [$a, $b, $c] = Product::factory()->count(3)->create();
        $order = Order::factory()->create(['delivery_date' => today()->subDays(3)]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $c->id, 'weight_kg' => 5]);
        $cancelled = Order::factory()->create(['delivery_date' => today()->subDays(3), 'status' => 'cancelled']);
        OrderItem::factory()->create(['order_id' => $cancelled->id, 'product_id' => $b->id, 'weight_kg' => 50]);

        $this->getJson('/api/products')->assertJsonPath('items.*.id', [$c->id, $a->id, $b->id]);
    }

    public function test_a_page_past_the_end_returns_the_last_page(): void
    {
        Product::factory()->count(8)->create();

        $this->getJson('/api/products?page=99&limit=6')->assertOk()
            ->assertJson(['page' => 2, 'totalPages' => 2])
            ->assertJsonCount(2, 'items');
    }

    public function test_search_and_category_filter_products(): void
    {
        Product::factory()->create(['name' => 'Cải ngọt', 'category' => 'leafy']);
        Product::factory()->create(['name' => 'Cải xanh', 'category' => 'leafy']);
        Product::factory()->create(['name' => 'Mồng tơi', 'category' => 'soup']);

        $this->getJson('/api/products?q=cải')->assertJson(['total' => 2]);
        $this->getJson('/api/products?category=soup')->assertJson(['total' => 1])->assertJsonPath('items.0.name', 'Mồng tơi');
        $this->getJson('/api/products?q=100%25')->assertJson(['total' => 0, 'totalPages' => 0, 'page' => 1]);
    }

    public function test_invalid_paging_is_rejected(): void
    {
        $this->getJson('/api/products?limit=51')->assertUnprocessable()->assertJsonValidationErrors('limit');
        $this->getJson('/api/products?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/products?category=fruit')->assertUnprocessable()->assertJsonValidationErrors('category');
    }

    public function test_baskets_are_paged_active_only_with_their_items(): void
    {
        $product = Product::factory()->create(['name' => 'Rau muống']);
        Basket::factory()->count(5)->create()->each(fn (Basket $basket) => $basket->items()->create(['product_id' => $product->id, 'quantity' => 2, 'allow_substitute' => true]));
        Basket::factory()->create(['is_active' => false]);

        $this->getJson('/api/baskets?active=true&page=2&limit=4')->assertOk()
            ->assertJson(['page' => 2, 'limit' => 4, 'total' => 5, 'totalPages' => 2])
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.items.0', ['quantity' => 2, 'name' => 'Rau muống', 'imageUrl' => null]);
    }

    public function test_home_embeds_the_requested_pages_of_products_and_baskets(): void
    {
        Product::factory()->count(8)->create();
        Basket::factory()->count(5)->create();

        $response = $this->get('/?trang=2&trang_gio=2')->assertOk();

        $this->assertSame(2, $response->viewData('products')['page']);
        $this->assertCount(2, $response->viewData('products')['items']);
        $this->assertSame(2, $response->viewData('baskets')['page']);
        $response->assertSee('Trang rau đang có')->assertSee('Trang giỏ rau');
    }

    public function test_order_form_preselects_the_basket_from_the_link(): void
    {
        [, $second] = Basket::factory()->count(2)->sequence(['price' => 40000], ['price' => 60000])->create();

        $this->get('/dat-hang?goi='.$second->id)->assertOk()->assertViewHas('preselectedBasket', fn ($basket) => $basket['id'] === $second->id);
        $this->get('/dat-hang?goi=999999')->assertOk()->assertViewHas('preselectedBasket', null);
    }
}
