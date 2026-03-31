<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add FK columns for tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('tat_legal_compliance')->constrained('ticket_statuses')->nullOnDelete();
            $table->index('status_id');
        });

        // Add FK columns for contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('status_id')->nullable()->after('description')->constrained('contract_statuses')->nullOnDelete();
            $table->foreignId('financial_impact_id')->nullable()->after('document_type')->constrained('financial_impacts')->nullOnDelete();

            $table->index('status_id');
            $table->index('financial_impact_id');
        });

        // Add FK column for reminder_logs table
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('user_id')->constrained('reminder_types')->nullOnDelete();
            $table->index('type_id');
        });

        // Migrate existing ENUM data to FK
        $this->migrateTicketStatuses();
        $this->migrateContractStatuses();
        $this->migrateFinancialImpacts();
        $this->migrateReminderTypes();

        // Drop old ENUM columns
        try {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        } catch (\Exception $e) {
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        try {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropIndex(['financial_impact']);
            });
        } catch (\Exception $e) {
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['status', 'financial_impact']);
        });

        try {
            Schema::table('reminder_logs', function (Blueprint $table) {
                $table->dropIndex(['type']);
            });
        } catch (\Exception $e) {
        }

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Rename FK columns to original names
        Schema::table('tickets', function (Blueprint $table) {
            $table->renameColumn('status_id', 'status_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('status_id', 'status_id');
            $table->renameColumn('financial_impact_id', 'financial_impact_id');
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->renameColumn('type_id', 'type_id');
        });
    }

    private function migrateTicketStatuses(): void
    {
        $statusMap = DB::table('ticket_statuses')->pluck('id', 'code');

        foreach ($statusMap as $code => $id) {
            DB::table('tickets')
                ->where('status', $code)
                ->update(['status_id' => $id]);
        }
    }

    private function migrateContractStatuses(): void
    {
        $statusMap = DB::table('contract_statuses')->pluck('id', 'code');

        foreach ($statusMap as $code => $id) {
            DB::table('contracts')
                ->where('status', $code)
                ->update(['status_id' => $id]);
        }
    }

    private function migrateFinancialImpacts(): void
    {
        $impactMap = DB::table('financial_impacts')->pluck('id', 'code');

        foreach ($impactMap as $code => $id) {
            DB::table('contracts')
                ->where('financial_impact', $code)
                ->update(['financial_impact_id' => $id]);
        }
    }

    private function migrateReminderTypes(): void
    {
        $typeMap = DB::table('reminder_types')->pluck('id', 'code');

        foreach ($typeMap as $code => $id) {
            DB::table('reminder_logs')
                ->where('type', $code)
                ->update(['type_id' => $id]);
        }
    }

    public function down(): void
    {
        // Recreate ENUM columns
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('status', ['open', 'on_process', 'rejected', 'done', 'closed'])->default('open')->after('tat_legal_compliance');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active')->after('description');
            $table->enum('financial_impact', ['income', 'expenditure'])->nullable()->after('document_type');
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->enum('type', ['email', 'notification'])->after('user_id');
        });

        // Drop FK columns
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropForeign(['financial_impact_id']);
            $table->dropColumn(['status_id', 'financial_impact_id']);
        });

        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });
    }
};
