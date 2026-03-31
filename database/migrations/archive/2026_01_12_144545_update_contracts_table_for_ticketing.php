<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Drop partner relationship
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');

            // Add ticket relationship
            $table->foreignId('ticket_id')->nullable()->unique()->after('id')->constrained()->cascadeOnDelete();

            // Update status enum - remove 'draft', contract only created when ticket approved
            $table->dropColumn('status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active')->after('description');

            // Add termination tracking
            $table->timestamp('terminated_at')->nullable()->after('status');
            $table->text('termination_reason')->nullable()->after('terminated_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Restore partner_id
            $table->foreignId('partner_id')->after('contract_number')->constrained()->cascadeOnDelete();

            // Remove ticket relationship
            $table->dropForeign(['ticket_id']);
            $table->dropColumn('ticket_id');

            // Remove termination fields
            $table->dropColumn(['terminated_at', 'termination_reason']);

            // Restore old status enum
            $table->dropColumn('status');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft')->after('description');
        });
    }
};
