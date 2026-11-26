<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Người giao hàng: vai trò mới, một ngày nghỉ, đơn gắn người giao và lần "không giao được" gần nhất.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','operator','shipper') NOT NULL DEFAULT 'operator'");

        Schema::table('users', function (Blueprint $table) {
            // ponytail: một ngày nghỉ mỗi người; cần lịch nghỉ nhiều ngày thì tách bảng.
            $table->date('off_date')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delivery_issue')->nullable();
            $table->dateTime('delivery_issue_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipper_id');
            $table->dropColumn(['delivery_issue', 'delivery_issue_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('off_date');
        });

        DB::table('users')->where('role', 'shipper')->update(['role' => 'operator']);
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','operator') NOT NULL DEFAULT 'operator'");
    }
};
