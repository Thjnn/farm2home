<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsCatalog;
use Tests\TestCase;

class BuildsCatalogTest extends TestCase
{
    use BuildsCatalog, RefreshDatabase;

    public function test_catalog_fixture_is_consistent(): void
    {
        $this->buildCatalog();

        $this->assertTrue($this->pickupZone->isPickup());
        $this->assertFalse($this->zone->isPickup());
        $this->assertCount(3, $this->basket->items);
        $this->assertSame('0.200', $this->hl->fresh()->pack_weight_kg);
        $this->assertSame(['CN', 'HL', 'RM'], $this->nh02->products->pluck('code')->sort()->values()->all());
        $this->assertTrue(password_verify('password', $this->admin->password));
    }
}
