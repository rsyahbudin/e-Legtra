<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drop duplicate snapshot columns from LGL_CONTRACT_MASTER.
     * These values are now read from the associated ticket's dynamic answers.
     */
    public function up(): void
    {
        Schema::table('LGL_CONTRACT_MASTER', function (Blueprint $table) {
            $table->dropColumn([
                'CONTR_PROP_DOC_TITLE',
                'CONTR_HAS_FIN_IMPACT',
                'CONTR_TAT_LGL_COMPLNCE',
                'CONTR_DOC_DRAFT_PATH',
                'CONTR_DOC_REQUIRED_PATH',
                'CONTR_DOC_APPROVAL_PATH',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_CONTRACT_MASTER', function (Blueprint $table) {
            $table->string('CONTR_PROP_DOC_TITLE')->nullable();
            $table->boolean('CONTR_HAS_FIN_IMPACT')->default(false);
            $table->boolean('CONTR_TAT_LGL_COMPLNCE')->default(false);
            $table->string('CONTR_DOC_DRAFT_PATH')->nullable();
            $table->json('CONTR_DOC_REQUIRED_PATH')->nullable();
            $table->string('CONTR_DOC_APPROVAL_PATH')->nullable();
        });
    }
};
