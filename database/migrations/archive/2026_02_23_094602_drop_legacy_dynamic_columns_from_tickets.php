<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop legacy columns that are now handled by the dynamic
     * LGL_FORM_QUESTION / LGL_TICKET_ANSWER system.
     */
    public function up(): void
    {
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            $table->dropColumn([
                // Perjanjian/NDA conditional fields
                'TCKT_COUNTERPART_NAME',
                'TCKT_AGREE_START_DT',
                'TCKT_AGREE_DURATION',
                'TCKT_IS_AUTO_RENEW',
                'TCKT_RENEW_PERIOD',
                'TCKT_RENEW_NOTIF_DAYS',
                'TCKT_AGREE_END_DT',
                'TCKT_TERMINATE_NOTIF_DT',
                // Surat Kuasa conditional fields
                'TCKT_GRANTOR',
                'TCKT_GRANTEE',
                'TCKT_GRANT_START_DT',
                'TCKT_GRANT_END_DT',
                // Pre-done / finalization questions
                'TCKT_POST_QUEST_1',
                'TCKT_POST_QUEST_2',
                'TCKT_POST_QUEST_3',
                'TCKT_POST_RMK',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            // Perjanjian/NDA
            $table->string('TCKT_COUNTERPART_NAME')->nullable();
            $table->date('TCKT_AGREE_START_DT')->nullable();
            $table->string('TCKT_AGREE_DURATION')->nullable();
            $table->boolean('TCKT_IS_AUTO_RENEW')->default(false);
            $table->string('TCKT_RENEW_PERIOD')->nullable();
            $table->integer('TCKT_RENEW_NOTIF_DAYS')->nullable();
            $table->date('TCKT_AGREE_END_DT')->nullable();
            $table->date('TCKT_TERMINATE_NOTIF_DT')->nullable();
            // Surat Kuasa
            $table->string('TCKT_GRANTOR')->nullable();
            $table->string('TCKT_GRANTEE')->nullable();
            $table->date('TCKT_GRANT_START_DT')->nullable();
            $table->date('TCKT_GRANT_END_DT')->nullable();
            // Pre-done
            $table->boolean('TCKT_POST_QUEST_1')->nullable();
            $table->boolean('TCKT_POST_QUEST_2')->nullable();
            $table->boolean('TCKT_POST_QUEST_3')->nullable();
            $table->text('TCKT_POST_RMK')->nullable();
        });
    }
};
