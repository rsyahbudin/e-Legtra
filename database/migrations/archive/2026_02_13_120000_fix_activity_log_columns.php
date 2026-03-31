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
        // Ensure table exists (it should be LGL_USER_ADTRL_LOG by now)
        if (Schema::hasTable('LGL_USER_ADTRL_LOG')) {
            Schema::table('LGL_USER_ADTRL_LOG', function (Blueprint $table) {

                // Rename loggable_type -> LOG_SUBJECT_TYPE
                if (Schema::hasColumn('LGL_USER_ADTRL_LOG', 'loggable_type') && ! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_SUBJECT_TYPE')) {
                    $table->renameColumn('loggable_type', 'LOG_SUBJECT_TYPE');
                }

                // Rename action -> LOG_EVENT
                if (Schema::hasColumn('LGL_USER_ADTRL_LOG', 'action') && ! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_EVENT')) {
                    $table->renameColumn('action', 'LOG_EVENT');
                }

                // Rename loggable_id -> LOG_SUBJECT_ID
                if (Schema::hasColumn('LGL_USER_ADTRL_LOG', 'loggable_id') && ! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_SUBJECT_ID')) {
                    $table->renameColumn('loggable_id', 'LOG_SUBJECT_ID');
                }

                // Rename user_id -> LOG_CAUSER_ID
                if (Schema::hasColumn('LGL_USER_ADTRL_LOG', 'user_id') && ! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_CAUSER_ID')) {
                    $table->renameColumn('user_id', 'LOG_CAUSER_ID');
                }

                // Add missing columns if they don't exist
                if (! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_CAUSER_TYPE')) {
                    $table->string('LOG_CAUSER_TYPE')->nullable();
                }

                if (! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_PROPERTIES')) {
                    $table->json('LOG_PROPERTIES')->nullable();
                }

                if (! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_NAME')) {
                    $table->string('LOG_NAME')->nullable();
                }

                if (! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_DESC')) {
                    $table->text('LOG_DESC')->nullable();
                }

                if (! Schema::hasColumn('LGL_USER_ADTRL_LOG', 'LOG_BATCH_UUID')) {
                    $table->uuid('LOG_BATCH_UUID')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way fix
    }
};
