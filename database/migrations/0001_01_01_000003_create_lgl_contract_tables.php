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
        // 1. Contract Master
        Schema::create('LGL_CONTRACT_MASTER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('TCKT_ID')->nullable();
            $table->string('CONTR_NO')->unique();
            $table->string('CONTR_AGREE_NAME')->nullable();
            $table->string('CONTR_PROP_DOC_TITLE')->nullable();
            $table->unsignedBigInteger('CONTR_DOC_TYPE_ID')->nullable();
            $table->unsignedInteger('CONTR_TAT_LGL_COMPLNCE')->nullable();
            $table->unsignedBigInteger('CONTR_DIV_ID')->nullable();
            $table->unsignedBigInteger('CONTR_DEPT_ID')->nullable();
            $table->unsignedBigInteger('CONTR_PIC_ID')->nullable();
            $table->timestamp('CONTR_START_DT')->nullable();
            $table->timestamp('CONTR_END_DT')->nullable();
            $table->boolean('CONTR_IS_AUTO_RENEW')->default(false);
            $table->text('CONTR_DESC')->nullable();
            $table->unsignedBigInteger('CONTR_STS_ID')->nullable();
            $table->string('CONTR_HAS_FIN_IMPACT')->nullable();
            $table->timestamp('CONTR_TERMINATE_DT')->nullable();
            $table->text('CONTR_TERMINATE_REASON')->nullable();
            $table->string('CONTR_DIR_SHARE_LINK')->nullable();
            $table->string('CONTR_DOC_DRAFT_PATH')->nullable();
            $table->string('CONTR_DOC_REQUIRED_PATH')->nullable();
            $table->string('CONTR_DOC_APPROVAL_PATH')->nullable();

            $table->unsignedBigInteger('CONTR_CREATED_BY')->nullable();
            $table->unsignedBigInteger('CONTR_UPDATED_BY')->nullable();
            $table->timestamp('CONTR_CREATED_DT')->nullable();
            $table->timestamp('CONTR_UPDATED_DT')->nullable();

            $table->foreign('TCKT_ID')->references('LGL_ROW_ID')->on('LGL_TICKET_MASTER')->onDelete('set null');
            $table->foreign('CONTR_DOC_TYPE_ID')->references('LGL_ROW_ID')->on('LGL_DOC_TYPE_MASTER')->onDelete('set null');
            $table->foreign('CONTR_DIV_ID')->references('LGL_ROW_ID')->on('LGL_DIVISION')->onDelete('set null');
            $table->foreign('CONTR_DEPT_ID')->references('LGL_ROW_ID')->on('LGL_DEPARTMENT')->onDelete('set null');
            $table->foreign('CONTR_STS_ID')->references('LGL_ID')->on('LGL_LOV_MASTER')->onDelete('set null');
            $table->foreign('CONTR_PIC_ID')->references('LGL_ROW_ID')->on('LGL_USER')->onDelete('set null');
            $table->foreign('CONTR_CREATED_BY')->references('LGL_ROW_ID')->on('LGL_USER')->onDelete('set null');
        });

        // 2. Reminder Logs
        Schema::create('LGL_REMINDER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('LGL_ROW_ID_CONTRACT');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('type_id');
            $table->integer('days_remaining')->nullable();
            $table->timestamp('SENT_AT')->nullable();
            $table->unsignedBigInteger('REF_REM_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_REM_UPDATED_BY')->nullable();
            $table->timestamp('REF_REM_CREATED_DT')->nullable();
            $table->timestamp('REF_REM_UPDATED_DT')->nullable();

            $table->foreign('LGL_ROW_ID_CONTRACT')->references('LGL_ROW_ID')->on('LGL_CONTRACT_MASTER')->onDelete('cascade');
            $table->foreign('user_id')->references('LGL_ROW_ID')->on('LGL_USER')->onDelete('cascade');
            $table->foreign('type_id')->references('LGL_ROW_ID')->on('LGL_REF_REMINDER_TYPE')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LGL_REMINDER');
        Schema::dropIfExists('LGL_CONTRACT_MASTER');
    }
};
