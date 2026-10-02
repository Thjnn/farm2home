<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sổ điểm thành viên (D19) + yêu cầu đổi quà trên đơn (D24) + đánh dấu gói rau tặng (D23).
     */
    public function up(): void
    {
        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->integer('points');
            $table->enum('reason', ['order_delivered', 'packaging_returned', 'redeemed', 'refunded']);
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('packaging_return_id')->nullable()->constrained('packaging_returns');
            $table->string('reward', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // D20: mỗi đơn tối đa một dòng cho mỗi lý do.
            $table->unique(['order_id', 'reason']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('requested_reward', 40)->nullable()->after('note');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('fulfilled_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('requested_reward');
        });

        Schema::dropIfExists('point_transactions');
    }
};
