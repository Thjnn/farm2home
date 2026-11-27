<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Khóa bí mật cho link khách xem / tự hủy đơn. Đơn cũ được điền khóa bằng DB::table để không đổi updated_at
     * (nếu đổi, chuông thông báo admin sẽ báo mọi đơn cũ là "vừa đổi trạng thái").
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('access_key', 32)->nullable()->after('code');
        });

        // eachById, không phải each(): each() phân trang bằng offset nên bỏ sót dòng khi đang điền đúng cột lọc.
        DB::table('orders')->whereNull('access_key')->select('id')
            ->eachById(fn (object $row) => DB::table('orders')->where('id', $row->id)->update(['access_key' => Str::random(24)]));
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('access_key');
        });
    }
};
