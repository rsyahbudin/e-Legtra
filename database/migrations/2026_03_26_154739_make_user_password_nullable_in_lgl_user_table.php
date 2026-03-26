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
        Schema::table('LGL_USER', function (Blueprint $table) {
            $table->string('USER_PASSWORD')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_USER', function (Blueprint $table) {
            $table->string('USER_PASSWORD')->nullable(false)->change();
        });
    }
};
