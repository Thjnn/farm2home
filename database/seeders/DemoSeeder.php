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
        self::convertSampleFarmersToCooperative();
    }

    /**
     * Đối tác cung ứng giờ là HTX: NH01 thành HTX Long Bình mẫu, NH02/NH03 ngưng hợp tác (giữ lịch sử đơn và lô cũ).
     * Bỏ qua khi không có dữ liệu mẫu NH01.
     */
    public static function convertSampleFarmersToCooperative(): void
    {
        if (! DB::table('farmers')->where('code', 'NH01')->exists()) {
            return;
        }

        DB::table('farmers')->where('code', 'NH01')->update([
            'code' => 'HTX01',
            'name' => 'HTX Long Bình',
            'representative_name' => 'Người đại diện (mẫu)',
            'address' => 'Xã Long Bình, Gò Công, Đồng Tháp',
            'growing_area' => 'Long Bình, Gò Công, Đồng Tháp',
            'area_m2' => null,
            'farming_method' => null,
            'note' => 'Dữ liệu mẫu: HTX Long Bình chưa xác nhận hợp tác; người đại diện và số điện thoại là giả.',
            'updated_at' => now(),
        ]);

        DB::table('farmers')->whereIn('code', ['NH02', 'NH03'])->update(['status' => 'inactive', 'updated_at' => now()]);
    }
}
