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
        Schema::table('contracts', function (Blueprint $table) {
            // Agreement Information
            $table->string('agreement_name')->after('contract_number');
            $table->string('proposed_document_title')->nullable()->after('agreement_name');

            // Document Classification
            $table->enum('document_type', ['nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'lainnya'])
                ->default('lainnya')
                ->after('proposed_document_title');

            // Financial & Compliance
            $table->enum('financial_impact', ['income', 'expenditure'])->nullable()->after('document_type');
            $table->boolean('tat_legal_compliance')->nullable()->after('financial_impact');

            // File Paths
            $table->string('draft_document_path')->nullable()->after('document_path');
            $table->json('mandatory_documents_path')->nullable()->after('draft_document_path');
            $table->string('approval_document_path')->nullable()->after('mandatory_documents_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'agreement_name',
                'proposed_document_title',
                'document_type',
                'financial_impact',
                'tat_legal_compliance',
                'draft_document_path',
                'mandatory_documents_path',
                'approval_document_path',
            ]);
        });
    }
};
