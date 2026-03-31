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
        // 1. Roles
        Schema::create('LGL_ROLE', function (Blueprint $table) {
            $table->id('ROLE_ID');
            $table->string('ROLE_NAME');
            $table->string('ROLE_SLUG')->unique();
            $table->string('ROLE_DESCRIPTION')->nullable();
            $table->string('GUARD_NAME')->default('web');
            $table->boolean('IS_ACTIVE')->default(true);
            $table->unsignedBigInteger('REF_ROLE_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_ROLE_UPDATED_BY')->nullable();
            $table->timestamp('REF_ROLE_CREATED_DT')->nullable();
            $table->timestamp('REF_ROLE_UPDATED_DT')->nullable();
        });

        // 2. Permissions
        Schema::create('LGL_PERMISSION', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('PERMISSION_ID')->nullable();
            $table->string('PERMISSION_NAME');
            $table->string('PERMISSION_CODE')->unique();
            $table->string('PERMISSION_GROUP')->nullable();
            $table->text('PERMISSION_DESC')->nullable();
            $table->string('GUARD_NAME')->default('web');
            $table->boolean('IS_ACTIVE')->default(true);
            $table->unsignedBigInteger('REF_PERM_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_PERM_UPDATED_BY')->nullable();
            $table->timestamp('REF_PERM_CREATED_DT')->nullable();
            $table->timestamp('REF_PERM_UPDATED_DT')->nullable();
        });

        // 3. Role-Permission Pivot
        Schema::create('LGL_ROLE_PERMISSION', function (Blueprint $table) {
            $table->unsignedBigInteger('ROLE_ID');
            $table->unsignedBigInteger('PERMISSION_ID');
            $table->unsignedBigInteger('REF_RP_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_RP_UPDATED_BY')->nullable();
            $table->timestamp('REF_RP_CREATED_DT')->nullable();
            $table->timestamp('REF_RP_UPDATED_DT')->nullable();

            $table->primary(['ROLE_ID', 'PERMISSION_ID']);
            $table->foreign('ROLE_ID')->references('ROLE_ID')->on('LGL_ROLE')->onDelete('cascade');
            $table->foreign('PERMISSION_ID')->references('LGL_ROW_ID')->on('LGL_PERMISSION')->onDelete('cascade');
        });

        // 4. User
        Schema::create('LGL_USER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('USER_FULLNAME');
            $table->string('USER_NAME')->nullable();
            $table->string('USER_EMAIL')->unique();
            $table->string('USER_PASSWORD')->nullable(); // SSO support
            $table->string('USER_ID')->unique(); // NIK
            $table->unsignedBigInteger('USER_ROLE_ID')->nullable();
            $table->unsignedBigInteger('DIV_ID')->nullable();
            $table->unsignedBigInteger('DEPT_ID')->nullable();
            $table->string('USER_REMEMBER_TOKEN', 100)->nullable();
            $table->text('USER_TWO_FACTOR_SECRET')->nullable();
            $table->text('USER_TWO_FACTOR_RECOVERY_CODES')->nullable();
            $table->timestamp('USER_TWO_FACTOR_CONFIRMED_DT')->nullable();
            $table->timestamp('USER_EMAIL_VERIFIED_DT')->nullable();
            $table->unsignedBigInteger('USER_CREATED_BY')->nullable();
            $table->unsignedBigInteger('USER_UPDATED_BY')->nullable();
            $table->timestamp('USER_CREATED_DT')->nullable();
            $table->timestamp('USER_UPDATED_DT')->nullable();

            $table->foreign('USER_ROLE_ID')->references('ROLE_ID')->on('LGL_ROLE')->onDelete('set null');
        });

        // 5. System Config
        Schema::create('LGL_SYS_CONFIG', function (Blueprint $table) {
            $table->id('CONFIG_ID');
            $table->string('CONFIG_KEY')->unique();
            $table->text('CONFIG_VALUE')->nullable();
            $table->string('type')->default('string');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('REF_CONFIG_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_CONFIG_UPDATED_BY')->nullable();
            $table->timestamp('REF_CONFIG_CREATED_DT')->nullable();
            $table->timestamp('REF_CONFIG_UPDATED_DT')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LGL_SYS_CONFIG');
        Schema::dropIfExists('LGL_USER');
        Schema::dropIfExists('LGL_ROLE_PERMISSION');
        Schema::dropIfExists('LGL_PERMISSION');
        Schema::dropIfExists('LGL_ROLE');
    }
};
