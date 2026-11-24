<?php

namespace Tests\Unit\Support;

use App\Support\SupplyMessageRules;
use App\Support\SupplyMessageScore;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SupplyMessageRulesTest extends TestCase
{
    private const PRODUCTS = ['RM' => 'Rau muống', 'CN' => 'Cải ngọt', 'CX' => 'Cải xanh', 'RD' => 'Rau dền'];

    private function parse(string $message): array
    {
        // Thứ Ba 24/11/2026.
        return SupplyMessageRules::parse($message, self::PRODUCTS, CarbonImmutable::parse('2026-11-24'));
    }

    public function test_reads_product_kg_and_tomorrow(): void
    {
        $this->assertSame([['product' => 'CN', 'kg' => 30.0, 'date' => '2026-11-25']], $this->parse('mai có 30 ký cải ngọt'));
    }

    public function test_splits_several_vegetables_and_reads_units_without_diacritics(): void
    {
        $this->assertSame(
            [['product' => 'RM', 'kg' => 20.0, 'date' => '2026-11-26'], ['product' => 'CX', 'kg' => 15.0, 'date' => '2026-11-26']],
            $this->parse('mốt muong 2 yen, cai be xanh 15kg'),
        );
    }

    public function test_weekday_and_day_month_dates(): void
    {
        $this->assertSame('2026-11-28', $this->parse('t7 có 10kg cải ngọt')[0]['date']);
        $this->assertSame('2026-11-30', $this->parse('thứ hai có 10kg cải ngọt')[0]['date']);
        $this->assertSame(['product' => 'CN', 'kg' => 10.0, 'date' => '2026-12-01'], $this->parse('1/12 có 10kg cải ngọt')[0]);
    }

    public function test_decimal_comma_is_one_number(): void
    {
        $this->assertSame([['product' => 'CN', 'kg' => 22.5, 'date' => '2026-11-25']], $this->parse('mai 22,5kg cải ngọt'));
    }

    public function test_missing_parts_stay_null_and_den_alone_is_not_a_vegetable(): void
    {
        $this->assertSame([['product' => null, 'kg' => 40.0, 'date' => '2026-11-25']], $this->parse('mai 40 ky'));
        $this->assertSame([['product' => 'RD', 'kg' => null, 'date' => null]], $this->parse('rau dền đến chiều mới có'));
        $this->assertSame([], $this->parse('Ok em'));
    }

    public function test_score_counts_correct_wrong_auto_and_handoff(): void
    {
        $item = ['product' => 'CN', 'kg' => 30, 'date' => '2026-11-25'];
        $score = SupplyMessageScore::score([
            ['expected' => [$item], 'handoff' => false, 'predicted' => [$item], 'handed_off' => false],
            ['expected' => [$item], 'handoff' => false, 'predicted' => [['kg' => 3] + $item], 'handed_off' => false],
            ['expected' => [], 'handoff' => true, 'predicted' => [], 'handed_off' => true],
            ['expected' => [$item], 'handoff' => false, 'predicted' => [$item], 'handed_off' => true],
        ]);

        $this->assertSame(
            ['n' => 4, 'correct' => 2, 'correct_pct' => 50.0, 'wrong_auto' => 1, 'wrong_auto_pct' => 25.0, 'handed_off' => 2, 'handed_off_pct' => 50.0, 'read_exact' => 2, 'complete' => 3, 'read_exact_pct' => 66.7],
            $score,
        );
    }
}
