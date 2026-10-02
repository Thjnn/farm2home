<?php

use App\Support\SqlFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Build the schema from database/farm2home.sql on an empty database.
     * A database that already imported the SQL file is left untouched.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        DB::unprepared('SET FOREIGN_KEY_CHECKS = 0');

        foreach (SqlFile::schemaStatements(database_path('farm2home.sql')) as $statement) {
            DB::unprepared($statement);
        }
    }

    /**
     * Baseline schema: intentionally not reversible.
     */
    public function down(): void
    {
        //
    }
};
