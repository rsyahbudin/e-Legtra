<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('LGL_FORM_SECTION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('SECT_CODE', 50)->unique();
            $table->string('SECT_LABEL', 200);
            $table->string('SECT_DESCRIPTION', 500)->nullable();
            $table->integer('SECT_SORT_ORDER')->default(0);
            $table->boolean('SECT_IS_ACTIVE')->default(true);
            $table->boolean('SECT_SHOW_ON_CREATE')->default(true);
            $table->boolean('SECT_SHOW_ON_DETAIL')->default(true);
            $table->timestamp('SECT_CREATED_DT')->nullable();
            $table->timestamp('SECT_UPDATED_DT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LGL_FORM_SECTION');
    }
};
