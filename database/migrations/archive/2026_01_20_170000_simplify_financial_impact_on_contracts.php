<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add boolean column
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('has_financial_impact')->default(false)->after('status_id');
        });

        // 2. Migrate data: If financial_impact_id was present, it implies IMPACT (true)
        // We lose the distinction of Income vs Expenditure as requested.
        DB::table('contracts')
            ->whereNotNull('financial_impact_id')
            ->update(['has_financial_impact' => true]);

        // 3. Drop FK and Column
        try {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropForeign(['financial_impact_id']);
            });
        } catch (\Exception $e) {
        }

        try {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropIndex(['financial_impact_id']);
            });
        } catch (\Exception $e) {
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('financial_impact_id');
        });

        // 4. Drop the lookup table
        Schema::dropIfExists('financial_impacts');
    }

    public function down(): void
    {
        // 1. Recreate lookup table
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

        // Reseed basic values
        DB::table('financial_impacts')->insert([
            ['code' => 'income', 'name' => 'Income', 'name_id' => 'Pemasukan', 'color' => 'green', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'expenditure', 'name' => 'Expenditure', 'name_id' => 'Pengeluaran', 'color' => 'red', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Re-add column
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('financial_impact_id')->nullable()->after('status_id')->constrained('financial_impacts')->nullOnDelete();
        });

        // Data restoration is partial/lossy (mapped to null or default)
        // We cannot know if it was Income or Expenditure, so we leave it NULL or check constraints if possible.
        // For simplicity in down(), we leave it null.

        // 3. Drop boolean
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('has_financial_impact');
        });
    }
};
