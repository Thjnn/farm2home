<?php

use App\Support\AddressEncryption;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mã hoá địa chỉ khách: chuỗi mã hoá dài hơn 255 ký tự nên đổi cột sang TEXT trước.
     */
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->text('address')->nullable()->change());
        Schema::table('orders', fn (Blueprint $table) => $table->text('delivery_address')->nullable()->change());

        AddressEncryption::encryptPlaintextRows();
    }

    public function down(): void
    {
        AddressEncryption::decryptAllRows();

        Schema::table('customers', fn (Blueprint $table) => $table->string('address', 255)->nullable()->change());
        Schema::table('orders', fn (Blueprint $table) => $table->string('delivery_address', 255)->nullable()->change());
    }
};
