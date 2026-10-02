<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_happy_path_transitions(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Packing));
        $this->assertTrue(OrderStatus::Packing->canTransitionTo(OrderStatus::Delivering));
        $this->assertTrue(OrderStatus::Delivering->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_every_open_status_can_be_cancelled(): void
    {
        foreach ([OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Delivering] as $status) {
            $this->assertTrue($status->canTransitionTo(OrderStatus::Cancelled));
        }
    }

    public function test_closed_statuses_are_terminal_and_steps_cannot_be_skipped(): void
    {
        $this->assertSame([], OrderStatus::Delivered->next());
        $this->assertSame([], OrderStatus::Cancelled->next());
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_only_cancelled_orders_are_excluded_from_demand(): void
    {
        $this->assertTrue(OrderStatus::Pending->countsAsDemand());
        $this->assertTrue(OrderStatus::Delivered->countsAsDemand());
        $this->assertFalse(OrderStatus::Cancelled->countsAsDemand());
    }
}
