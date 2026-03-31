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
            if (Schema::hasColumn('LGL_USER', 'USER_NAME')) {
                $table->dropColumn('USER_NAME');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_USER', function (Blueprint $table) {
            if (! Schema::hasColumn('LGL_USER', 'USER_NAME')) {
                $table->string('USER_NAME')->nullable()->after('USER_FULLNAME');
            }
        });
    }
};
