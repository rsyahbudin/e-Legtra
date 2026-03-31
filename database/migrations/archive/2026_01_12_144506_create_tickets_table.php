<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();

            // Auto-filled from user account (readonly)
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->cascadeOnDelete();

            // Common fields for all ticket types
            $table->boolean('has_financial_impact')->default(false); // Q3: Financial Impact (Yes/No)
            $table->string('proposed_document_title'); // Q4: Usulan Judul Dokumen (REQUIRED)
            $table->string('draft_document_path')->nullable(); // Q5: Draft Usulan Dokumen (upload file)

            // Q6: Jenis Dokumen (REQUIRED)
            $table->enum('document_type', [
                'perjanjian', // Perjanjian/Adendum/Amandemen
                'nda', // Perjanjian Kerahasiaan (NDA)
                'surat_kuasa', // Surat Kuasa
                'pendapat_hukum', // Pendapat Hukum
                'surat_pernyataan', // Surat Pernyataan
                'surat_lainnya', // Surat lainnya
            ]);

            // Conditional fields for: 'perjanjian' OR 'nda'
            $table->string('counterpart_name')->nullable(); // Q7: Counterpart / Nama Pihak lainnya
            $table->date('agreement_start_date')->nullable(); // Q8: Tanggal Perkiraan Mulainya Perjanjian/NDA
            $table->string('agreement_duration')->nullable(); // Q9: Jangka Waktu Perjanjian/NDA (text field, e.g., "2 tahun")
            $table->boolean('is_auto_renewal')->default(false); // Q10: Pembaruan Otomatis (YES/NO)

            // Sub-conditional: if is_auto_renewal = true
            $table->enum('renewal_period', ['yearly', 'monthly', 'weekly'])->nullable(); // Q11: Periode Pembaruan Otomatis
            $table->integer('renewal_notification_days')->nullable(); // Q12: Jangka Waktu Notifikasi Sebelum Pembaruan (in days)

            $table->date('agreement_end_date')->nullable(); // Q13: Tanggal Berakhirnya Perjanjian
            $table->integer('termination_notification_days')->nullable(); // Q14: Jangka Waktu Notifikasi Sebelum Pengakhiran

            // Conditional fields for: 'surat_kuasa'
            $table->string('kuasa_pemberi')->nullable(); // Q7 (Surat Kuasa): Pemberi Kuasa
            $table->string('kuasa_penerima')->nullable(); // Q8 (Surat Kuasa): Penerima Kuasa
            $table->date('kuasa_start_date')->nullable(); // Q9 (Surat Kuasa): Tanggal Perkiraan Mulainya Pemberian Kuasa
            $table->date('kuasa_end_date')->nullable(); // Q10 (Surat Kuasa): Tanggal Berakhirnya Kuasa

            // Common for all types (but different question numbers depending on document type)
            $table->boolean('tat_legal_compliance')->default(false); // Kesesuaian dengan Turn-Around-Time Legal
            $table->json('mandatory_documents_path')->nullable(); // Dokumen Wajib (multiple files)
            $table->string('approval_document_path')->nullable(); // Legal Request Permit/Approval

            // Ticket workflow fields
            $table->enum('status', ['open', 'on_process', 'rejected', 'done', 'closed'])->default('open');
            $table->timestamp('reviewed_at')->nullable(); // When legal first reviews
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); // Legal user who reviews

            // Aging tracking
            $table->timestamp('aging_start_at')->nullable(); // When status changes to 'on_process'
            $table->timestamp('aging_end_at')->nullable(); // When status changes to 'done'
            $table->integer('aging_duration')->nullable(); // Duration in hours (calculated)

            // Rejection
            $table->text('rejection_reason')->nullable();

            // Audit fields
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Indexes for performance
            $table->index('status');
            $table->index('document_type');
            $table->index('created_by');
            $table->index('division_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
