<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LGL_FORM_QUESTION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');

            $table->unsignedBigInteger('QUEST_DOC_TYPE_ID')->nullable();
            $table->foreign('QUEST_DOC_TYPE_ID')->references('LGL_ROW_ID')->on('LGL_DOC_TYPE_MASTER')->nullOnDelete();

            $table->string('QUEST_SECTION', 50); // 'form' or 'finalization'
            $table->string('QUEST_CODE', 100)->unique();
            $table->string('QUEST_LABEL', 500);
            $table->string('QUEST_TYPE', 30); // boolean, text, date, number, select, file

            $table->json('QUEST_OPTIONS')->nullable(); // For select: [{"value":"x","label":"Y"}]
            $table->boolean('QUEST_IS_REQUIRED')->default(true);
            $table->integer('QUEST_SORT_ORDER')->default(0);
            $table->boolean('QUEST_IS_ACTIVE')->default(true);

            // Dependency system for conditional display
            $table->string('QUEST_DEPENDS_ON', 100)->nullable(); // Code of parent question
            $table->string('QUEST_DEPENDS_VALUE', 100)->nullable(); // Required value of parent

            // UI helpers
            $table->string('QUEST_PLACEHOLDER', 500)->nullable();
            $table->string('QUEST_DESCRIPTION', 1000)->nullable();

            // File-specific
            $table->integer('QUEST_MAX_SIZE_KB')->nullable(); // Max file size in KB
            $table->string('QUEST_ACCEPT', 200)->nullable(); // e.g. ".pdf,.doc,.docx"
            $table->boolean('QUEST_IS_MULTIPLE')->default(false); // Multiple file upload

            $table->timestamp('QUEST_CREATED_DT')->nullable();
            $table->timestamp('QUEST_UPDATED_DT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LGL_FORM_QUESTION');
    }
};
