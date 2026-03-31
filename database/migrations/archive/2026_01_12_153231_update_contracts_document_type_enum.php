<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update enum to include 'perjanjian'
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('document_type')->default('lainnya')->change();
        });
    }

    public function down(): void
    {
        // Revert back
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('document_type')->default('lainnya')->change();
        });
    }
};
