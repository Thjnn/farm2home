<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Cách đọc tin nhắn báo sản lượng "đơn giản" (không AI): dò tên rau, số kg và ngày bằng từ khóa.
 * Dùng làm mốc so sánh cho AI và làm phương án dự phòng khi không gọi được AI.
 */
final class SupplyMessageRules
{
    /**
     * Tên gọi khác của từng loại rau (đã bỏ dấu). Tên chính lấy từ bảng products.
     */
    private const ALIASES = [
        'RM' => ['rau muong', 'muong'],
        'CN' => ['cai ngot'],
        'CX' => ['cai be xanh', 'cai xanh', 'cai be'],
        'MT' => ['mong toi', 'mung toi'],
        // Không có "den" riêng: không dấu trùng với "đến".
        'RD' => ['rau den', 'den do'],
        'XL' => ['xa lach', 'xalach'],
        'HL' => ['hanh la', 'hanh'],
        'GD' => ['gia do', 'gia dau'],
        'CC' => ['ca chua'],
    ];

    private const WEEKDAYS = ['hai' => 1, 'ba' => 2, 'tu' => 3, 'nam' => 4, 'sau' => 5, 'bay' => 6];

    /**
     * @param  array<string, string>  $products  [code => tên]
     * @return list<array{product: ?string, kg: ?float, date: ?string}>
     */
    public static function parse(string $message, array $products, CarbonImmutable $today): array
    {
        $text = mb_strtolower($message);
        $defaultDate = self::findDate($text, $today);
        $aliases = self::aliases($products);
        $items = [];

        // Dấu phẩy giữa hai chữ số là số thập phân ("22,5kg"), không phải chỗ tách dòng.
        foreach (preg_split('/(?<!\d),|,(?!\d)|[;\n+]|\s(?:và|va|với|voi|còn|con)\s/u', $text) as $segment) {
            $date = self::findDate($segment, $today) ?? $defaultDate;
            $ascii = ' '.Str::ascii(self::stripDates($segment)).' ';
            $product = null;

            foreach ($aliases as $alias => $code) {
                if (preg_match('/[^a-z]'.preg_quote($alias, '/').'[^a-z]/', $ascii) === 1) {
                    $product = $code;
                    break;
                }
            }

            $kg = self::findKg($ascii);

            if ($product !== null || $kg !== null) {
                $items[] = ['product' => $product, 'kg' => $kg, 'date' => $date];
            }
        }

        return $items;
    }

    /**
     * Tên dài trước để "cai be xanh" không bị "cai be" ăn mất.
     *
     * @param  array<string, string>  $products
     * @return array<string, string> [alias => code]
     */
    private static function aliases(array $products): array
    {
        $aliases = [];
        foreach ($products as $code => $name) {
            foreach ([mb_strtolower(Str::ascii($name)), ...(self::ALIASES[$code] ?? [])] as $alias) {
                $aliases[$alias] = $code;
            }
        }
        uksort($aliases, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $aliases;
    }

    private static function findKg(string $ascii): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|ky|ki|kilo|k|can|yen|ta)?(?![a-z])/', $ascii, $match) !== 1) {
            return null;
        }

        $value = (float) str_replace(',', '.', $match[1]);

        return match ($match[2] ?? '') {
            'yen' => $value * 10,
            'ta' => $value * 100,
            default => $value,
        };
    }

    private static function findDate(string $text, CarbonImmutable $today): ?string
    {
        if (preg_match('/(\d{1,2})\/(\d{1,2})/', $text, $match) === 1) {
            $date = rescue(fn () => CarbonImmutable::create($today->year, (int) $match[2], (int) $match[1]), null, false);

            return $date?->lt($today->subDays(30)) ? $date->addYear()->toDateString() : $date?->toDateString();
        }

        $ascii = ' '.Str::ascii($text).' ';
        $weekday = match (true) {
            preg_match('/[^a-z0-9](?:t|thu)\s?([2-7])(?![0-9])/', $ascii, $match) === 1 => (int) $match[1] - 1,
            preg_match('/[^a-z]thu (hai|ba|tu|nam|sau|bay)[^a-z]/', $ascii, $match) === 1 => self::WEEKDAYS[$match[1]],
            preg_match('/[^a-z](cn|chu nhat)[^a-z]/', $ascii) === 1 => 7,
            default => null,
        };
        if ($weekday !== null) {
            $days = ($weekday - $today->dayOfWeekIso + 7) % 7;

            return $today->addDays($days === 0 ? 7 : $days)->toDateString();
        }

        // "mốt" phải có dấu: không dấu "mot" trùng với "một".
        return match (true) {
            preg_match('/mốt|ngày kia|[^a-z]ngay kia[^a-z]/u', ' '.$text.' ') === 1 => $today->addDays(2)->toDateString(),
            preg_match('/[^a-z](mai)[^a-z]/', $ascii) === 1 => $today->addDay()->toDateString(),
            preg_match('/[^a-z](nay)[^a-z]/', $ascii) === 1 => $today->toDateString(),
            default => null,
        };
    }

    private static function stripDates(string $text): string
    {
        $text = preg_replace('/\d{1,2}\/\d{1,2}/', ' ', $text);

        return preg_replace('/(?<![\p{L}\d])(?:t|thứ|thu)\s?[2-7](?!\d)/u', ' ', $text);
    }
}
