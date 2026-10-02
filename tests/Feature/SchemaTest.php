<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_never_run_against_the_dev_database(): void
    {
        $this->assertSame('farm2home_test', DB::connection()->getDatabaseName());
    }

    public function test_baseline_migration_creates_all_24_tables(): void
    {
        foreach ([
            'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
            'delivery_zones', 'farmers', 'products', 'farmer_products', 'baskets', 'basket_items',
            'customers', 'subscriptions', 'orders', 'order_items', 'supply_reports',
            'forecasts', 'harvest_plans', 'harvest_plan_items', 'batches', 'order_item_batches',
            'feedbacks', 'waste_logs', 'packaging_returns',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }
    }

    public function test_demo_seeder_loads_the_sample_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(155, DB::table('orders')->count());
        $this->assertSame(466, DB::table('order_items')->count());
        $this->assertSame(146, DB::table('batches')->count());
        $this->assertSame(45, DB::table('feedbacks')->count());
        $this->assertSame('Rau muống', DB::table('products')->where('code', 'RM')->value('name'));
    }
}
