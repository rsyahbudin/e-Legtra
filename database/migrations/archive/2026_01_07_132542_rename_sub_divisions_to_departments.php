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
        // Rename the table
        Schema::rename('sub_divisions', 'departments');

        // Rename the foreign key column in contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('sub_division_id', 'department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back the foreign key column
        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('department_id', 'sub_division_id');
        });

        // Rename back the table
        Schema::rename('departments', 'sub_divisions');
    }
};
