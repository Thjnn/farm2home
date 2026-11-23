<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nhật ký sửa lô: ai sửa, lúc nào, trường nào từ giá trị nào sang giá trị nào. Không sửa âm thầm hồ sơ lô.
     */
    public function up(): void
    {
        Schema::create('batch_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('changes')->comment('{"field": [old, new]}');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_changes');
    }
};
