<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tài khoản nhận tiền thu mua của nông hộ (số tài khoản lưu mã hoá), ảnh vườn rau và ảnh sản phẩm của lô cho trang QR.
     */
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('farming_method');
            $table->text('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_holder', 150)->nullable()->after('bank_account_number');
            $table->json('farm_photos')->nullable()->after('bank_account_holder');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('qc_note');
        });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder', 'farm_photos']);
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
