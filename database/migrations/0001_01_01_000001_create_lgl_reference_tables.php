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
        // 1. Divisions
        Schema::create('LGL_DIVISION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('REF_DIV_ID')->unique();
            $table->string('REF_DIV_NAME');
            $table->string('REF_DIV_DESC')->nullable();
            $table->boolean('IS_ACTIVE')->default(true);
            $table->unsignedBigInteger('REF_DIV_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_DIV_UPDATED_BY')->nullable();
            $table->timestamp('REF_DIV_CREATED_DT')->nullable();
            $table->timestamp('REF_DIV_UPDATED_DT')->nullable();
        });

        // 2. Departments
        Schema::create('LGL_DEPARTMENT', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('DIV_ID');
            $table->string('REF_DEPT_ID')->unique();
            $table->string('REF_DEPT_NAME');
            $table->string('REF_DEPT_DESC')->nullable();
            $table->string('email')->nullable();
            $table->text('cc_emails')->nullable(); // JSON/Text
            $table->boolean('IS_ACTIVE')->default(true);
            $table->unsignedBigInteger('REF_DEPT_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_DEPT_UPDATED_BY')->nullable();
            $table->timestamp('REF_DEPT_CREATED_DT')->nullable();
            $table->timestamp('REF_DEPT_UPDATED_DT')->nullable();

            $table->foreign('DIV_ID')->references('LGL_ROW_ID')->on('LGL_DIVISION')->onDelete('cascade');
        });

        // 3. Document Types
        Schema::create('LGL_DOC_TYPE_MASTER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('code')->unique();
            $table->string('REF_DOC_TYPE_NAME');
            $table->string('description')->nullable();
            $table->boolean('requires_contract')->default(true);
            $table->boolean('REF_DOC_TYPE_IS_ACTIVE')->default(true);
            $table->unsignedInteger('DOC_TYPE_SORT_ORDER')->default(0);
            $table->unsignedBigInteger('REF_DOC_TYPE_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_DOC_TYPE_UPDATED_BY')->nullable();
            $table->timestamp('REF_DOC_TYPE_CREATED_DT')->nullable();
            $table->timestamp('REF_DOC_TYPE_UPDATED_DT')->nullable();
        });

        // 4. LOV Master
        Schema::create('LGL_LOV_MASTER', function (Blueprint $table) {
            $table->id('LGL_ID');
            $table->string('LOV_TYPE', 50);
            $table->string('LOV_VALUE', 100);
            $table->string('LOV_DISPLAY_NAME', 100);
            $table->string('DESCRIPTION')->nullable();
            $table->unsignedTinyInteger('LOV_SEQ_NO')->nullable();
            $table->boolean('IS_ACTIVE')->default(true);
            $table->timestamp('LOV_CREATED_DT')->nullable();
            $table->timestamp('LOV_UPDATED_DT')->nullable();

            $table->unique(['LOV_TYPE', 'LOV_VALUE']);
        });

        // 5. Reminder Types
        Schema::create('LGL_REF_REMINDER_TYPE', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('REF_REMIND_TYPE_ID')->unique();
            $table->string('REF_REMIND_TYPE_NAME');
            $table->string('REF_REMIND_TYPE_DESC')->nullable();
            $table->boolean('REF_REMIND_TYPE_IS_ACTIVE')->default(true);
            $table->unsignedBigInteger('REF_REMIND_TYPE_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_REMIND_TYPE_UPDATED_BY')->nullable();
            $table->timestamp('REF_REMIND_TYPE_CREATED_DT')->nullable();
            $table->timestamp('REF_REMIND_TYPE_UPDATED_DT')->nullable();
        });

        // Add FKs to LGL_USER that couldn't be added in Step 1 due to circular dependency or missing tables
        Schema::table('LGL_USER', function (Blueprint $table) {
            $table->foreign('DIV_ID')->references('LGL_ROW_ID')->on('LGL_DIVISION')->onDelete('set null');
            $table->foreign('DEPT_ID')->references('LGL_ROW_ID')->on('LGL_DEPARTMENT')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_USER', function (Blueprint $table) {
            $table->dropForeign(['DEPT_ID']);
            $table->dropForeign(['DIV_ID']);
        });
        Schema::dropIfExists('LGL_LOV_MASTER');
        Schema::dropIfExists('LGL_DOC_TYPE_MASTER');
        Schema::dropIfExists('LGL_DEPARTMENT');
        Schema::dropIfExists('LGL_DIVISION');
    }
};
