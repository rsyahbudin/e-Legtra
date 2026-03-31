<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. LGL_DIVISION: Rename is_active to IS_ACTIVE
        if (Schema::hasTable('LGL_DIVISION')) {
            Schema::table('LGL_DIVISION', function (Blueprint $table) {
                if (Schema::hasColumn('LGL_DIVISION', 'is_active') && ! Schema::hasColumn('LGL_DIVISION', 'IS_ACTIVE')) {
                    $table->renameColumn('is_active', 'IS_ACTIVE');
                }
            });
        }

        // 2. LGL_DEPARTMENT: Add IS_ACTIVE if missing
        if (Schema::hasTable('LGL_DEPARTMENT')) {
            Schema::table('LGL_DEPARTMENT', function (Blueprint $table) {
                if (! Schema::hasColumn('LGL_DEPARTMENT', 'IS_ACTIVE')) {
                    $table->boolean('IS_ACTIVE')->default(true)->after('cc_emails');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('LGL_DIVISION')) {
            Schema::table('LGL_DIVISION', function (Blueprint $table) {
                if (Schema::hasColumn('LGL_DIVISION', 'IS_ACTIVE')) {
                    $table->renameColumn('IS_ACTIVE', 'is_active');
                }
            });
        }

        if (Schema::hasTable('LGL_DEPARTMENT')) {
            Schema::table('LGL_DEPARTMENT', function (Blueprint $table) {
                if (Schema::hasColumn('LGL_DEPARTMENT', 'IS_ACTIVE')) {
                    $table->dropColumn('IS_ACTIVE');
                }
            });
        }
    }
};
