<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phần khách muốn mua nhưng hết hàng (khách hỏi qua Zalo/điện thoại mà không nhận đơn được).
     * Người điều phối ghi tay mỗi buổi giao; đơn hệ thống không thấy phần nhu cầu bị cắt này.
     */
    public function up(): void
    {
        Schema::create('unmet_demands', function (Blueprint $table) {
            $table->id();
            $table->date('delivery_date');
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity_kg', 8, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['delivery_date', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmet_demands');
    }
};
