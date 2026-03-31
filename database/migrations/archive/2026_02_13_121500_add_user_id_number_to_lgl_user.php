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
            if (! Schema::hasColumn('LGL_USER', 'USER_ID_NUMBER')) {
                $table->string('USER_ID_NUMBER', 20)->nullable()->after('USER_EMAIL')->comment('NIK User');
            }
            if (! Schema::hasColumn('LGL_USER', 'USER_EMAIL_VERIFIED_DT')) {
                $table->timestamp('USER_EMAIL_VERIFIED_DT')->nullable()->after('USER_PASSWORD');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_USER', function (Blueprint $table) {
            $table->dropColumn(['USER_ID_NUMBER', 'USER_EMAIL_VERIFIED_DT']);
        });
    }
};
