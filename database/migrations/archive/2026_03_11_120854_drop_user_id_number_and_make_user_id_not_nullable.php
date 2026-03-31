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
        Schema::table('LGL_USER', function (Blueprint $table) {
            // Drop USER_ID_NUMBER column if it exists
            if (Schema::hasColumn('LGL_USER', 'USER_ID_NUMBER')) {
                $table->dropColumn('USER_ID_NUMBER');
            }

            // Make USER_ID not nullable
            if (Schema::hasColumn('LGL_USER', 'USER_ID')) {
                $table->string('USER_ID', 10)->nullable(false)->comment('NIK / Employee ID')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_USER', function (Blueprint $table) {
            // Revert USER_ID to nullable
            if (Schema::hasColumn('LGL_USER', 'USER_ID')) {
                $table->string('USER_ID', 10)->nullable()->comment('NIK / Employee ID')->change();
            }

            // Re-add USER_ID_NUMBER column
            if (! Schema::hasColumn('LGL_USER', 'USER_ID_NUMBER')) {
                $table->string('USER_ID_NUMBER', 20)->nullable()->after('USER_EMAIL')->comment('NIK User');
            }
        });
    }
};
