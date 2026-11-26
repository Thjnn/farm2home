<?php

namespace Tests\Unit;

use App\Support\ShipperSplit;
use PHPUnit\Framework\TestCase;

class ShipperSplitTest extends TestCase
{
    public function test_two_zones_go_to_two_empty_shippers_biggest_first(): void
    {
        $this->assertSame([10 => 1, 20 => 2], ShipperSplit::assign([20 => 3, 10 => 5], [], [1 => 0, 2 => 0]));
    }

    public function test_each_zone_goes_to_the_least_loaded_shipper(): void
    {
        $this->assertSame([1 => 1, 2 => 2, 3 => 2], ShipperSplit::assign([1 => 4, 2 => 3, 3 => 2], [], [1 => 0, 2 => 0]));
    }

    public function test_sticky_zone_keeps_its_shipper_and_counts_toward_load(): void
    {
        $this->assertSame([1 => 1, 2 => 2], ShipperSplit::assign([1 => 6, 2 => 2], [1 => 1], [1 => 0, 2 => 0]));
        $this->assertSame([5 => 2, 6 => 1], ShipperSplit::assign([5 => 1, 6 => 1], [5 => 2], [1 => 0, 2 => 3]));
    }

    public function test_existing_loads_are_respected(): void
    {
        $this->assertSame([7 => 2], ShipperSplit::assign([7 => 2], [], [1 => 6, 2 => 1]));
    }

    public function test_one_shipper_gets_every_zone(): void
    {
        $this->assertSame([1 => 9, 2 => 9], ShipperSplit::assign([1 => 1, 2 => 4], [], [9 => 0]));
    }

    public function test_no_working_shipper_assigns_nothing(): void
    {
        $this->assertSame([], ShipperSplit::assign([1 => 3], [], []));
    }
}
