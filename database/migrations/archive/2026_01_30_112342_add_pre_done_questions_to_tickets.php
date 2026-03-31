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
        Schema::table('tickets', function (Blueprint $table) {
            // Pertanyaan pre-done (khusus untuk perjanjian)
            // Nullable karena hanya mandatory untuk document_type = 'perjanjian'
            $table->boolean('pre_done_question_1')->nullable()->after('rejection_reason');
            $table->boolean('pre_done_question_2')->nullable()->after('pre_done_question_1');
            $table->boolean('pre_done_question_3')->nullable()->after('pre_done_question_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['pre_done_question_1', 'pre_done_question_2', 'pre_done_question_3']);
        });
    }
};
