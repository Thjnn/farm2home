<?php

namespace App\Support;

/**
 * Một quà trong danh mục đổi điểm, đọc từ config('farm2home.loyalty.rewards').
 */
final readonly class Reward
{
    public const TYPE_GIFT = 'gift';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPE_DISCOUNT = 'discount';

    public function __construct(
        public string $key,
        public string $label,
        public int $points,
        public string $type,
        public int $packs = 0,
        public int $amount = 0,
    ) {}

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $rewards = [];

        foreach (config('farm2home.loyalty.rewards') as $key => $reward) {
            $rewards[] = new self($key, $reward['label'], $reward['points'], $reward['type'], $reward['packs'] ?? 0, $reward['amount'] ?? 0);
        }

        return $rewards;
    }

    public static function find(?string $key): ?self
    {
        foreach (self::all() as $reward) {
            if ($reward->key === $key) {
                return $reward;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(config('farm2home.loyalty.rewards'));
    }

    public function isGift(): bool
    {
        return $this->type === self::TYPE_GIFT;
    }
}
