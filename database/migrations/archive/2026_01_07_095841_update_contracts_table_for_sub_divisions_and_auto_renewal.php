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
            $table->foreignId('sub_division_id')->nullable()->after('division_id')->constrained('sub_divisions')->nullOnDelete();
            $table->boolean('is_auto_renewal')->default(false)->after('end_date');
            $table->date('end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['sub_division_id']);
            $table->dropColumn(['sub_division_id', 'is_auto_renewal']);
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
