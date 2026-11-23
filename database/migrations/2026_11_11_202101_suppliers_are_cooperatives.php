<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đối tác cung ứng là HTX: mỗi dòng `farmers` là một HTX. FARM2HOME phân bổ, thanh toán và nhận báo sản lượng theo HTX;
     * HTX tự chia cho hộ thành viên ngoài hệ thống. Lô hàng ghi thêm hộ trồng nếu HTX khai.
     */
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('representative_name', 150)->nullable()->after('name')->comment('Người đại diện HTX nhận tin Zalo');
            $table->unsignedSmallInteger('member_households')->nullable()->after('area_m2')->comment('Số hộ thành viên HTX khai');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->string('grower_name', 150)->nullable()->after('farmer_id')->comment('Hộ trồng do HTX khai khi giao, tùy chọn');
        });

        DemoSeeder::convertSampleFarmersToCooperative();
    }

    public function down(): void
    {
        Schema::table('batches', fn (Blueprint $table) => $table->dropColumn('grower_name'));
        Schema::table('farmers', fn (Blueprint $table) => $table->dropColumn(['representative_name', 'member_households']));
    }
};
