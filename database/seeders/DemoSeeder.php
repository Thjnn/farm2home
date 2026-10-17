<?php

namespace Database\Seeders;

use App\Support\AddressEncryption;
use App\Support\SqlFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Load the sample data section of database/farm2home.sql into an empty database.
     */
    public function run(): void
    {
        if (DB::table('orders')->exists()) {
            throw new RuntimeException('DemoSeeder chỉ chạy trên DB trống (bảng orders đã có dữ liệu).');
        }

        foreach (SqlFile::sampleStatements(database_path('farm2home.sql')) as $statement) {
            DB::unprepared($statement);
        }

        // File SQL mẫu ghi dữ liệu thô: thêm khoá QR cho lô và mã hoá địa chỉ như dữ liệu thật.
        DB::table('batches')->whereNull('qr_key')->pluck('id')->each(
            fn (int $id) => DB::table('batches')->where('id', $id)->update(['qr_key' => Str::random(10)])
        );
        AddressEncryption::encryptPlaintextRows();
    }
}
