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
        // 1. Ticket Master
        Schema::create('LGL_TICKET_MASTER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('TCKT_NO')->unique();
            $table->unsignedBigInteger('DIV_ID');
            $table->unsignedBigInteger('DEPT_ID');
            $table->boolean('TCKT_HAS_FIN_IMPACT')->default(false);
            $table->string('TCKT_PROP_DOC_TITLE')->nullable();
            $table->string('TCKT_DOC_PATH')->nullable();
            $table->unsignedBigInteger('TCKT_DOC_TYPE_ID');
            $table->string('TCKT_COUNTERPART_NAME')->nullable();
            $table->timestamp('TCKT_AGREE_START_DT')->nullable();
            $table->string('TCKT_AGREE_DURATION')->nullable();
            $table->boolean('TCKT_IS_AUTO_RENEW')->default(false);
            $table->string('TCKT_RENEW_PERIOD')->nullable();
            $table->unsignedInteger('TCKT_RENEW_NOTIF_DAYS')->nullable();
            $table->timestamp('TCKT_AGREE_END_DT')->nullable();
            $table->unsignedInteger('TCKT_TERMINATE_NOTIF_DT')->nullable();
            $table->string('TCKT_GRANTOR')->nullable();
            $table->string('TCKT_GRANTEE')->nullable();
            $table->timestamp('TCKT_GRANT_START_DT')->nullable();
            $table->timestamp('TCKT_GRANT_END_DT')->nullable();
            $table->unsignedInteger('TCKT_TAT_LGL_COMPLNCE')->nullable();
            $table->unsignedBigInteger('TCKT_STS_ID');
            $table->string('TCKT_DOC_REQUIRED_PATH')->nullable();
            $table->string('TCKT_DOC_APPROVAL_PATH')->nullable();
            $table->timestamp('TCKT_REVIEWED_DT')->nullable();
            $table->unsignedBigInteger('TCKT_REVIEWED_BY')->nullable();
            $table->timestamp('TCKT_AGING_START_DT')->nullable();
            $table->timestamp('TCKT_AGING_END_DT')->nullable();
            $table->unsignedInteger('TCKT_AGING_DURATION')->nullable();
            $table->text('TCKT_REJECT_REASON')->nullable();
            $table->string('TCKT_POST_QUEST_1')->nullable();
            $table->string('TCKT_POST_QUEST_2')->nullable();
            $table->string('TCKT_POST_QUEST_3')->nullable();
            $table->text('TCKT_POST_RMK')->nullable();

            $table->unsignedBigInteger('TCKT_CREATED_BY')->nullable();
            $table->unsignedBigInteger('TCKT_UPDATED_BY')->nullable();
            $table->timestamp('TCKT_CREATED_DT')->nullable();
            $table->timestamp('TCKT_UPDATED_DT')->nullable();

            $table->foreign('DIV_ID')->references('LGL_ROW_ID')->on('LGL_DIVISION')->onDelete('cascade');
            $table->foreign('DEPT_ID')->references('LGL_ROW_ID')->on('LGL_DEPARTMENT')->onDelete('cascade');
            $table->foreign('TCKT_DOC_TYPE_ID')->references('LGL_ROW_ID')->on('LGL_DOC_TYPE_MASTER')->onDelete('cascade');
            $table->foreign('TCKT_STS_ID')->references('LGL_ID')->on('LGL_LOV_MASTER')->onDelete('cascade');
            $table->foreign('TCKT_CREATED_BY')->references('LGL_ROW_ID')->on('LGL_USER')->onDelete('set null');
        });

        // 2. Form Sections
        Schema::create('LGL_FORM_SECTION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('SECT_TITLE')->nullable(); // Legacy
            $table->string('SECT_CODE')->unique();
            $table->string('SECT_LABEL')->nullable();
            $table->string('SECT_DESCRIPTION')->nullable();
            $table->unsignedInteger('SECT_SORT_ORDER')->default(0);
            $table->boolean('SECT_IS_ACTIVE')->default(true);
            $table->boolean('SECT_SHOW_ON_CREATE')->default(true);
            $table->boolean('SECT_SHOW_ON_DETAIL')->default(true);
            $table->unsignedBigInteger('REF_SECTION_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_SECTION_UPDATED_BY')->nullable();
            $table->timestamp('SECT_CREATED_DT')->nullable();
            $table->timestamp('SECT_UPDATED_DT')->nullable();
        });

        // 3. Form Questions
        Schema::create('LGL_FORM_QUESTION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('QUEST_DOC_TYPE_ID')->nullable();
            $table->string('QUEST_SECTION'); // Link to SECT_CODE
            $table->string('QUEST_CODE')->unique();
            $table->string('QUEST_LABEL');
            $table->string('QUEST_TYPE'); // text, select, boolean, date
            $table->text('QUEST_OPTIONS')->nullable(); 
            $table->string('QUEST_WIDTH')->default('full'); 
            $table->boolean('QUEST_IS_REQUIRED')->default(false);
            $table->unsignedInteger('QUEST_SORT_ORDER')->default(0);
            $table->boolean('QUEST_IS_ACTIVE')->default(true);
            $table->string('QUEST_DEPENDS_ON')->nullable();
            $table->string('QUEST_DEPENDS_VALUE')->nullable();
            $table->string('QUEST_PLACEHOLDER')->nullable();
            $table->text('QUEST_DESCRIPTION')->nullable();
            $table->unsignedInteger('QUEST_MAX_SIZE_KB')->nullable();
            $table->string('QUEST_ACCEPT')->nullable();
            $table->boolean('QUEST_IS_MULTIPLE')->default(false);

            $table->unsignedBigInteger('REF_QUEST_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_QUEST_UPDATED_BY')->nullable();
            $table->timestamp('QUEST_CREATED_DT')->nullable();
            $table->timestamp('QUEST_UPDATED_DT')->nullable();

            $table->foreign('QUEST_DOC_TYPE_ID')->references('LGL_ROW_ID')->on('LGL_DOC_TYPE_MASTER')->onDelete('cascade');
        });

        // 4. Ticket Answers
        Schema::create('LGL_TICKET_ANSWER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('ANS_TICKET_ID');
            $table->unsignedBigInteger('ANS_QUESTION_ID');
            $table->text('ANS_VALUE')->nullable();
            $table->unsignedBigInteger('REF_ANS_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_ANS_UPDATED_BY')->nullable();
            $table->timestamp('ANS_CREATED_DT')->nullable();
            $table->timestamp('ANS_UPDATED_DT')->nullable();

            $table->foreign('ANS_TICKET_ID')->references('LGL_ROW_ID')->on('LGL_TICKET_MASTER')->onDelete('cascade');
            $table->foreign('ANS_QUESTION_ID')->references('LGL_ROW_ID')->on('LGL_FORM_QUESTION')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LGL_TICKET_ANSWER');
        Schema::dropIfExists('LGL_FORM_QUESTION');
        Schema::dropIfExists('LGL_FORM_SECTION');
        Schema::dropIfExists('LGL_TICKET_MASTER');
    }
};
