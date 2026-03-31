<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LGL_TICKET_ANSWER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');

            $table->unsignedBigInteger('ANS_TICKET_ID');
            $table->foreign('ANS_TICKET_ID')->references('LGL_ROW_ID')->on('LGL_TICKET_MASTER')->cascadeOnDelete();

            $table->unsignedBigInteger('ANS_QUESTION_ID');
            $table->foreign('ANS_QUESTION_ID')->references('LGL_ROW_ID')->on('LGL_FORM_QUESTION')->cascadeOnDelete();

            $table->text('ANS_VALUE')->nullable(); // Stores: string, file path, JSON array for multi-file

            $table->timestamp('ANS_CREATED_DT')->nullable();
            $table->timestamp('ANS_UPDATED_DT')->nullable();

            $table->unique(['ANS_TICKET_ID', 'ANS_QUESTION_ID'], 'uq_ticket_answer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LGL_TICKET_ANSWER');
    }
};
