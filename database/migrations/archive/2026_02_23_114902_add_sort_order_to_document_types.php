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
        Schema::table('LGL_DOC_TYPE_MASTER', function (Blueprint $table) {
            $table->integer('DOC_TYPE_SORT_ORDER')->default(0)->after('REF_DOC_TYPE_NAME');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_DOC_TYPE_MASTER', function (Blueprint $table) {
            $table->dropColumn('DOC_TYPE_SORT_ORDER');
        });
    }
};
