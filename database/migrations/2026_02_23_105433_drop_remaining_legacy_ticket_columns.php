<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop remaining legacy user-input columns that are now handled by the
     * dynamic LGL_FORM_QUESTION / LGL_TICKET_ANSWER system.
     *
     * Phase 2: Common fields (basic + supporting sections).
     */
    public function up(): void
    {
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            $table->dropColumn([
                // Basic section
                'TCKT_HAS_FIN_IMPACT',
                'TCKT_PAYMENT_TYPE',
                'TCKT_RECURRING_DESC',
                'TCKT_PROP_DOC_TITLE',
                'TCKT_DOC_PATH',
                // Supporting section
                'TCKT_TAT_LGL_COMPLNCE',
                'TCKT_DOC_REQUIRED_PATH',
                'TCKT_DOC_APPROVAL_PATH',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            $table->boolean('TCKT_HAS_FIN_IMPACT')->default(false);
            $table->string('TCKT_PAYMENT_TYPE')->nullable();
            $table->string('TCKT_RECURRING_DESC')->nullable();
            $table->string('TCKT_PROP_DOC_TITLE')->nullable();
            $table->string('TCKT_DOC_PATH')->nullable();
            $table->boolean('TCKT_TAT_LGL_COMPLNCE')->default(false);
            $table->json('TCKT_DOC_REQUIRED_PATH')->nullable();
            $table->string('TCKT_DOC_APPROVAL_PATH')->nullable();
        });
    }
};
