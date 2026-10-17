<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Địa chỉ giao hàng được lưu mã hoá (cast "encrypted" trên model, khoá là APP_KEY).
 */
final class AddressEncryption
{
    /**
     * @var list<array{0: string, 1: string}> [bảng, cột]
     */
    public const COLUMNS = [['customers', 'address'], ['orders', 'delivery_address']];

    /**
     * Mã hoá các địa chỉ còn ở dạng chữ thường (dữ liệu cũ, file SQL mẫu). Chạy lại nhiều lần không mã hoá chồng.
     */
    public static function encryptPlaintextRows(): int
    {
        $count = 0;

        foreach (self::COLUMNS as [$table, $column]) {
            DB::table($table)->whereNotNull($column)->select('id', $column)->chunkById(500, function ($rows) use ($table, $column, &$count) {
                foreach ($rows as $row) {
                    if (! self::isEncrypted($row->{$column})) {
                        DB::table($table)->where('id', $row->id)->update([$column => Crypt::encryptString($row->{$column})]);
                        $count++;
                    }
                }
            });
        }

        return $count;
    }

    /**
     * Giải mã ngược về chữ thường (chỉ dùng khi rollback migration).
     */
    public static function decryptAllRows(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            DB::table($table)->whereNotNull($column)->select('id', $column)->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    if (self::isEncrypted($row->{$column})) {
                        DB::table($table)->where('id', $row->id)->update([$column => Crypt::decryptString($row->{$column})]);
                    }
                }
            });
        }
    }

    private static function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
