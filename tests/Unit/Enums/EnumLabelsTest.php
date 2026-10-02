<?php

namespace Tests\Unit\Enums;

use App\Enums\CustomerSource;
use App\Enums\ForecastMethod;
use App\Enums\HarvestPlanStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\WasteReason;
use PHPUnit\Framework\TestCase;

class EnumLabelsTest extends TestCase
{
    public function test_there_are_17_enums_and_every_case_has_a_label(): void
    {
        $files = glob(dirname(__DIR__, 3).'/app/Enums/*.php');
        $this->assertCount(17, $files);

        foreach ($files as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');
            foreach ($enum::cases() as $case) {
                $this->assertNotSame('', trim($case->label()), "{$enum}::{$case->name} has no label");
            }
        }
    }

    public function test_values_match_the_mysql_enums(): void
    {
        $this->assertSame(['pending', 'confirmed', 'packing', 'delivering', 'delivered', 'cancelled'], array_column(OrderStatus::cases(), 'value'));
        $this->assertSame(['moving_average', 'exp_smoothing', 'tree_regression', 'manual'], array_column(ForecastMethod::cases(), 'value'));
        $this->assertSame(['cod', 'bank_transfer'], array_column(PaymentMethod::cases(), 'value'));
        $this->assertSame(['surplus', 'damaged', 'expired', 'discounted'], array_column(WasteReason::cases(), 'value'));
        $this->assertSame(['draft', 'confirmed', 'sent', 'completed'], array_column(HarvestPlanStatus::cases(), 'value'));
        $this->assertSame(['zalo', 'form', 'website', 'phone', 'referral'], array_column(CustomerSource::cases(), 'value'));
    }
}
