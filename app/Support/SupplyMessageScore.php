<?php

namespace App\Support;

/**
 * Chấm cách đọc tin nhắn sản lượng trên bộ tin mẫu đã có đáp án.
 *
 * - Xử lý đúng: tin đủ thông tin được đọc đúng hết và tự đi tiếp, hoặc tin thiếu/mâu thuẫn được chuyển cho người.
 * - Tự đi sai: không chuyển cho người nhưng đọc sai (lỗi nguy hiểm nhất, vì có thể lưu số sai).
 * - Chuyển người: tin bị đánh dấu cần người xem.
 */
final class SupplyMessageScore
{
    /**
     * @param  list<array{expected: list<array{product: string, kg: float|int, date: string}>, handoff: bool, predicted: list<array{product: ?string, kg: ?float, date: ?string}>, handed_off: bool}>  $cases
     * @return array{n: int, correct: int, correct_pct: ?float, wrong_auto: int, wrong_auto_pct: ?float, handed_off: int, handed_off_pct: ?float, read_exact: int, complete: int, read_exact_pct: ?float}
     */
    public static function score(array $cases): array
    {
        $n = count($cases);
        $correct = $wrongAuto = $handedOff = $readExact = $complete = 0;

        foreach ($cases as $case) {
            $matches = ! $case['handoff'] && self::key($case['expected']) === self::key($case['predicted']);
            $handedOff += $case['handed_off'] ? 1 : 0;

            if (! $case['handoff']) {
                $complete++;
                $readExact += $matches ? 1 : 0;
            }

            if ($case['handed_off']) {
                $correct += $case['handoff'] ? 1 : 0;
            } elseif ($matches) {
                $correct++;
            } else {
                $wrongAuto++;
            }
        }

        $pct = fn (int $count, int $of) => $of === 0 ? null : round(100 * $count / $of, 1);

        return [
            'n' => $n,
            'correct' => $correct, 'correct_pct' => $pct($correct, $n),
            'wrong_auto' => $wrongAuto, 'wrong_auto_pct' => $pct($wrongAuto, $n),
            'handed_off' => $handedOff, 'handed_off_pct' => $pct($handedOff, $n),
            'read_exact' => $readExact, 'complete' => $complete, 'read_exact_pct' => $pct($readExact, $complete),
        ];
    }

    /**
     * @param  list<array{product: ?string, kg: float|int|null, date: ?string}>  $items
     */
    private static function key(array $items): string
    {
        $keys = array_map(fn (array $item) => ($item['product'] ?? '?').'|'.($item['kg'] === null ? '?' : number_format((float) $item['kg'], 2)).'|'.($item['date'] ?? '?'), $items);
        sort($keys);

        return implode(';', $keys);
    }
}
