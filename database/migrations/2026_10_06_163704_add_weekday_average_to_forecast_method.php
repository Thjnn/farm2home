<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phương pháp "trung bình cùng thứ" (Phase 9, Task 7.5 cũ).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual','weekday_average') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM `forecasts` WHERE `method` = 'weekday_average'");
        DB::statement("ALTER TABLE `forecasts` MODIFY `method` ENUM('moving_average','exp_smoothing','tree_regression','manual') NOT NULL");
    }
};
