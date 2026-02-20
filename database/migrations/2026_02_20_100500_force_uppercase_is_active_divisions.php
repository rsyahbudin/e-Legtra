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
            // Force rename using raw SQL to bypass Laravel/MySQL case-insensitive column checks
            DB::statement('ALTER TABLE LGL_DIVISION CHANGE is_active IS_ACTIVE TINYINT(1) DEFAULT 1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('LGL_DIVISION')) {
            DB::statement('ALTER TABLE LGL_DIVISION CHANGE IS_ACTIVE is_active TINYINT(1) DEFAULT 1');
        }
    }
};
