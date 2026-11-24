<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Leafy = 'leafy';
    case Soup = 'soup';
    case RootFruit = 'root_fruit';
    case Herb = 'herb';

    public function label(): string
    {
        return match ($this) {
            self::Leafy => 'Rau ăn lá',
            self::Soup => 'Rau nấu canh',
            self::RootFruit => 'Củ quả',
            self::Herb => 'Gia vị',
        };
    }
}
