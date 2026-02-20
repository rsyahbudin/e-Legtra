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
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_TICKET_MASTER', 'payment_type')) {
                $table->renameColumn('payment_type', 'TCKT_PAYMENT_TYPE');
            }
            if (Schema::hasColumn('LGL_TICKET_MASTER', 'recurring_description')) {
                $table->renameColumn('recurring_description', 'TCKT_RECURRING_DESC');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_TICKET_MASTER', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_TICKET_MASTER', 'TCKT_PAYMENT_TYPE')) {
                $table->renameColumn('TCKT_PAYMENT_TYPE', 'payment_type');
            }
            if (Schema::hasColumn('LGL_TICKET_MASTER', 'TCKT_RECURRING_DESC')) {
                $table->renameColumn('TCKT_RECURRING_DESC', 'recurring_description');
            }
        });
    }
};
