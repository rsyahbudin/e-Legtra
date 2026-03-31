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
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('pic_id')->nullable()->change();
            $table->string('pic_name')->nullable()->after('pic_id');
            $table->string('pic_email')->nullable()->after('pic_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('pic_id')->nullable(false)->change();
            $table->dropColumn(['pic_name', 'pic_email']);
        });
    }
};
