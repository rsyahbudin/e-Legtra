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
        Schema::table('LGL_FORM_QUESTION', function (Blueprint $table) {
            // Options: 'full' (col-span-2), 'half' (col-span-1 default)
            $table->string('QUEST_WIDTH', 20)->default('full')->after('QUEST_TYPE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_FORM_QUESTION', function (Blueprint $table) {
            $table->dropColumn('QUEST_WIDTH');
        });
    }
};
