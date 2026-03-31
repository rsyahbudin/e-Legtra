<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0. Document Types (needed by tickets and contracts)
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('name_en', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('requires_contract')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::table('document_types')->insert([
            ['code' => 'perjanjian', 'name' => 'Perjanjian/Adendum/Amandemen', 'name_en' => 'Agreement/Addendum/Amendment', 'description' => 'Perjanjian kerjasama, adendum, atau amandemen legal', 'requires_contract' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'nda', 'name' => 'Perjanjian Kerahasiaan (NDA)', 'name_en' => 'Non-Disclosure Agreement (NDA)', 'description' => 'Perjanjian kerahasiaan informasi', 'requires_contract' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'surat_kuasa', 'name' => 'Surat Kuasa', 'name_en' => 'Power of Attorney', 'description' => 'Surat kuasa pemberian wewenang', 'requires_contract' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'pendapat_hukum', 'name' => 'Pendapat Hukum', 'name_en' => 'Legal Opinion', 'description' => 'Pendapat atau analisa hukum', 'requires_contract' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'surat_pernyataan', 'name' => 'Surat Pernyataan', 'name_en' => 'Statement Letter', 'description' => 'Surat pernyataan resmi', 'requires_contract' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'surat_lainnya', 'name' => 'Surat Lainnya', 'name_en' => 'Other Letters', 'description' => 'Dokumen legal lainnya', 'requires_contract' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 1. Ticket Statuses
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->string('name_id', 50);
            $table->string('color', 20)->default('neutral');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::table('ticket_statuses')->insert([
            ['code' => 'open', 'name' => 'Open', 'name_id' => 'Terbuka', 'color' => 'blue', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'on_process', 'name' => 'On Process', 'name_id' => 'Sedang Diproses', 'color' => 'yellow', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rejected', 'name' => 'Rejected', 'name_id' => 'Ditolak', 'color' => 'red', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'done', 'name' => 'Done', 'name_id' => 'Selesai', 'color' => 'green', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'closed', 'name' => 'Closed', 'name_id' => 'Ditutup', 'color' => 'neutral', 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Contract Statuses
        Schema::create('contract_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->string('name_id', 50);
            $table->string('color', 20)->default('neutral');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::table('contract_statuses')->insert([
            ['code' => 'active', 'name' => 'Active', 'name_id' => 'Aktif', 'color' => 'green', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'expired', 'name' => 'Expired', 'name_id' => 'Kadaluarsa', 'color' => 'red', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'terminated', 'name' => 'Terminated', 'name_id' => 'Dihentikan', 'color' => 'neutral', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Financial Impacts
        Schema::create('financial_impacts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->string('name_id', 50);
            $table->string('color', 20)->default('neutral');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::table('financial_impacts')->insert([
            ['code' => 'income', 'name' => 'Income', 'name_id' => 'Pemasukan', 'color' => 'green', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'expenditure', 'name' => 'Expenditure', 'name_id' => 'Pengeluaran', 'color' => 'red', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 4. Reminder Types
        Schema::create('reminder_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        DB::table('reminder_types')->insert([
            ['code' => 'email', 'name' => 'Email', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'notification', 'name' => 'In-App Notification', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_types');
        Schema::dropIfExists('financial_impacts');
        Schema::dropIfExists('contract_statuses');
        Schema::dropIfExists('ticket_statuses');
        Schema::dropIfExists('document_types');
    }
};
