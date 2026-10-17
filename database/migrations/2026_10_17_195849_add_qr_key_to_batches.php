<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Khoá ngẫu nhiên trong link QR: mã lô theo quy luật nên đoán được, khoá thì không.
     */
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('qr_key', 16)->nullable()->after('code');
        });

        // Để nullable: farm2home.sql chèn lô không có cột này, DemoSeeder điền khoá sau khi nạp.
        DB::table('batches')->whereNull('qr_key')->pluck('id')->each(
            fn (int $id) => DB::table('batches')->where('id', $id)->update(['qr_key' => Str::random(10)])
        );
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('qr_key');
        });
    }
};
