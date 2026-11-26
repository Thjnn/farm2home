<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\AmountToCollect;
use PHPUnit\Framework\TestCase;

class AmountToCollectTest extends TestCase
{
    private function order(PaymentMethod $method, PaymentStatus $status): Order
    {
        return (new Order)->forceFill(['total' => 85000, 'payment_method' => $method, 'payment_status' => $status]);
    }

    public function test_unpaid_cod_collects_the_total(): void
    {
        $this->assertSame(85000, AmountToCollect::for($this->order(PaymentMethod::Cod, PaymentStatus::Unpaid)));
    }

    public function test_paid_or_bank_transfer_collects_nothing(): void
    {
        $this->assertSame(0, AmountToCollect::for($this->order(PaymentMethod::Cod, PaymentStatus::Paid)));
        $this->assertSame(0, AmountToCollect::for($this->order(PaymentMethod::BankTransfer, PaymentStatus::Unpaid)));
    }
}
