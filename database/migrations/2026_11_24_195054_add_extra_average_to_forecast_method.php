<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cách tính thông thường để so với mô hình: đơn đã có + mức đặt thêm trung bình các buổi cùng thứ.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual','weekday_average','extra_average') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM `forecasts` WHERE `method` = 'extra_average'");
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual','weekday_average') NOT NULL");
    }
};
