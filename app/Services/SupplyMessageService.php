<?php

namespace App\Services;

use App\Models\Farmer;
use App\Models\Product;
use App\Support\SupplyMessageRules;
use Carbon\CarbonImmutable;

/**
 * Đọc tin nhắn Zalo báo sản lượng của HTX ("mai có 30 ký cải ngọt") thành các dòng sản lượng nháp.
 * AI chỉ đọc; người vận hành luôn xem và bấm xác nhận trước khi lưu. Dòng nào thiếu hoặc lạ thì đánh dấu cần người xử lý.
 */
class SupplyMessageService
{
    public const MAX_KG = 500;

    public function __construct(private GeminiClient $gemini) {}

    /**
     * @return array{method: 'ai'|'rules', items: list<array{product: ?string, kg: ?float, date: ?string, unsure: bool}>}
     */
    public function read(string $message, CarbonImmutable $now, bool $useAi = true): array
    {
        $items = $useAi ? $this->readWithAi($message, $now) : null;

        return $items === null
            ? ['method' => 'rules', 'items' => array_map(fn (array $item) => $item + ['unsure' => false], $this->readWithRules($message, $now))]
            : ['method' => 'ai', 'items' => $items];
    }

    /**
     * @return list<array{product: ?string, kg: ?float, date: ?string}>
     */
    public function readWithRules(string $message, CarbonImmutable $now): array
    {
        return SupplyMessageRules::parse($message, $this->products(), $now->startOfDay());
    }

    /**
     * null = không gọi được AI hoặc AI trả về không đọc được.
     *
     * @return list<array{product: ?string, kg: ?float, date: ?string, unsure: bool}>|null
     */
    public function readWithAi(string $message, CarbonImmutable $now): ?array
    {
        $json = $this->gemini->generate($this->prompt($now), $message, [
            'temperature' => 0,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => ['items' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'product' => ['type' => 'STRING', 'nullable' => true],
                        'kg' => ['type' => 'NUMBER', 'nullable' => true],
                        'date' => ['type' => 'STRING', 'nullable' => true],
                        'unsure' => ['type' => 'BOOLEAN'],
                    ],
                    'required' => ['product', 'kg', 'date', 'unsure'],
                ]]],
                'required' => ['items'],
            ],
        ], 'Đọc tin sản lượng');

        $decoded = $json === null ? null : json_decode($json, true);
        if (! is_array($decoded) || ! is_array($decoded['items'] ?? null)) {
            return null;
        }

        $codes = array_keys($this->products());

        return array_values(array_map(fn ($item) => [
            'product' => in_array($item['product'] ?? null, $codes, true) ? $item['product'] : null,
            'kg' => is_numeric($item['kg'] ?? null) ? round((float) $item['kg'], 2) : null,
            'date' => is_string($item['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item['date']) ? $item['date'] : null,
            'unsure' => (bool) ($item['unsure'] ?? false),
        ], array_filter($decoded['items'], 'is_array')));
    }

    /**
     * Lý do cần người xử lý cho từng dòng; null = dòng đủ thông tin, chỉ cần bấm xác nhận.
     *
     * @param  array{product: ?string, kg: ?float, date: ?string, unsure?: bool}  $item
     */
    public function reviewReason(array $item, CarbonImmutable $now, ?Farmer $farmer = null): ?string
    {
        $today = $now->startOfDay();

        return match (true) {
            $item['product'] === null => 'Không rõ loại rau',
            $item['kg'] === null || $item['kg'] <= 0 => 'Không rõ số kg',
            $item['kg'] > self::MAX_KG => 'Số kg quá lớn, hỏi lại HTX',
            $item['date'] === null => 'Không rõ ngày',
            CarbonImmutable::parse($item['date'])->lt($today) => 'Ngày đã qua',
            CarbonImmutable::parse($item['date'])->gt($today->addDays(7)) => 'Ngày quá xa (hơn 7 ngày)',
            $item['unsure'] ?? false => 'AI không chắc, cần xem lại',
            $farmer !== null && ! $farmer->products()->where('code', $item['product'])->exists() => 'HTX chưa đăng ký loại rau này',
            default => null,
        };
    }

    /**
     * @return array<string, string> [code => tên] các loại rau đang bán
     */
    private function products(): array
    {
        return Product::where('is_active', true)->orderBy('id')->pluck('name', 'code')->all();
    }

    private function prompt(CarbonImmutable $now): string
    {
        $products = collect($this->products())->map(fn (string $name, string $code) => "{$code}: {$name}")->implode("\n");
        $today = $now->locale('vi')->isoFormat('dddd, DD/MM/YYYY');

        return <<<PROMPT
        Bạn đọc tin nhắn Zalo của hợp tác xã rau báo sản lượng có thể giao cho Farm2Home.
        Hôm nay là {$today} ({$now->toDateString()}). Tin nhắn thường viết tắt, có thể không dấu.

        Các loại rau (chỉ dùng đúng mã bên trái):
        {$products}

        Trả về JSON {"items": [...]}, mỗi loại rau một dòng: product (mã rau), kg (số kg), date (YYYY-MM-DD, ngày có hàng), unsure.
        Quy tắc:
        - "ký", "kí", "kg", "k" là kg; 1 yến = 10 kg; 1 tạ = 100 kg. Số viết bằng chữ ("ba chục") thì đổi ra số.
        - "mai" = ngày mai, "mốt"/"ngày kia" = 2 ngày nữa, "t7"/"thứ 7" = thứ Bảy sắp tới, "cn" = Chủ nhật sắp tới.
        - Thiếu thông tin nào thì để null, KHÔNG đoán. Loại rau không có trong danh sách thì product = null.
        - "tầm 30", "khoảng 30" là ước lượng bình thường: kg = 30, unsure = false.
        - unsure = true khi tin mâu thuẫn hoặc chưa chắc có hàng: đổi ý giữa chừng, hai con số khác nhau cho một loại rau, "chưa biết", "để coi lại".
        - Tin chỉ hỏi han, chào hỏi hoặc báo nghỉ thì trả {"items": []}.
        PROMPT;
    }
}
