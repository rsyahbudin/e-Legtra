<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('LGL_DIVISION')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                // SQLite doesn't support CHANGE — column is already named correctly via migration
                return;
            }

            DB::statement('ALTER TABLE LGL_DIVISION CHANGE is_active IS_ACTIVE TINYINT(1) DEFAULT 1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('LGL_DIVISION')) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                return;
            }

            DB::statement('ALTER TABLE LGL_DIVISION CHANGE IS_ACTIVE is_active TINYINT(1) DEFAULT 1');
        }
    }
};
