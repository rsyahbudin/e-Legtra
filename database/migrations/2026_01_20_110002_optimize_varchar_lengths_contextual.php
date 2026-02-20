<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users Table - Context-specific lengths
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 100)->change(); // Realistic name length
            $table->string('email', 100)->change(); // MySQL index limit
            $table->string('password', 255)->change(); // Bcrypt hash + future-proof
        });

        // Settings Table
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key', 100)->change(); // Setting keys are short
            // value stays TEXT for email templates
        });

        // Divisions Table
        Schema::table('divisions', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->string('code', 20)->nullable()->change();
            // cc_emails stays TEXT (multiple emails)
        });

        // Departments Table
        Schema::table('departments', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('code', 20)->nullable()->change();
            // cc_emails stays TEXT (multiple emails)
        });

        // Roles Table
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('slug', 50)->change();
        });

        // Permissions Table
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('name', 100)->change();
            $table->string('slug', 100)->change();
            $table->string('group', 50)->change();
        });

        // Notifications Table
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type', 50)->change(); // notification types are short
            $table->string('title', 150)->change(); // notification titles
            // message stays TEXT (can be long)
        });

        // Activity Logs Table
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('event', 200)->nullable()->change(); // Action descriptions (event)
            // old_values and new_values stay JSON
        });

        // Tickets Table - Already optimized in previous migration
        // ticket_number: 20 ✓
        // proposed_document_title: 200 ✓
        // counterpart_name: 150 ✓
        // agreement_duration: 50 ✓
        // draft_document_path: 191 (MySQL index limit for file paths)
        // approval_document_path: 191
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('draft_document_path', 200)->nullable()->change();
            $table->string('approval_document_path', 200)->nullable()->change();
        });

        // Contracts Table - Apply context-specific lengths
        // contract_number: 20 ✓ (already optimized)
        // agreement_name: 200 ✓
        // description: 500 ✓
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('pic_name', 100)->nullable()->change();
            $table->string('pic_email', 100)->nullable()->change(); // MySQL index limit
            $table->string('document_path', 200)->nullable()->change();
            $table->string('draft_document_path', 200)->nullable()->change();
            $table->string('approval_document_path', 200)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert to VARCHAR(255) defaults
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('password')->change();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->change();
        });

        Schema::table('divisions', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('code')->nullable()->change();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('code')->nullable()->change();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('slug')->change();
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('slug')->change();
            $table->string('group')->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->change();
            $table->string('title')->change();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('action')->change();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('draft_document_path')->nullable()->change();
            $table->string('approval_document_path')->nullable()->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('pic_name')->nullable()->change();
            $table->string('pic_email')->nullable()->change();
            $table->string('document_path')->nullable()->change();
            $table->string('draft_document_path')->nullable()->change();
            $table->string('approval_document_path')->nullable()->change();
        });
    }
};
