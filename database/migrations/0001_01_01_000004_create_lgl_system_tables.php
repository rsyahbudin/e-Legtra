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
        // 1. Audit Logs (Spatie Activity Log replacement)
        Schema::create('LGL_USER_ADTRL_LOG', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->string('LOG_NAME')->nullable();
            $table->text('LOG_DESC');
            $table->string('LOG_SUBJECT_TYPE')->nullable();
            $table->string('LOG_EVENT')->nullable();
            $table->unsignedBigInteger('LOG_SUBJECT_ID')->nullable();
            $table->string('LOG_CAUSER_TYPE')->nullable();
            $table->unsignedBigInteger('LOG_CAUSER_ID')->nullable();
            $table->text('LOG_PROPERTIES')->nullable(); // Oracle maps JSON to CLOB/BLOB
            $table->uuid('LOG_BATCH_UUID')->nullable();
            $table->text('LOG_OLD_VALUES')->nullable();
            $table->text('LOG_NEW_VALUES')->nullable();
            $table->unsignedBigInteger('REF_CONTR_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_CONTR_UPDATED_BY')->nullable();
            $table->timestamp('REF_CONTR_CREATED_DT')->nullable();
            $table->timestamp('REF_CONTR_UPDATED_DT')->nullable();

            $table->index(['LOG_SUBJECT_TYPE', 'LOG_SUBJECT_ID'], 'adtrl_log_subject_index');
            $table->index(['LOG_CAUSER_TYPE', 'LOG_CAUSER_ID'], 'adtrl_log_causer_index');
        });

        // 2. Notifications
        Schema::create('LGL_NOTIFICATION_MASTER', function (Blueprint $table) {
            $table->id('LGL_ROW_ID');
            $table->unsignedBigInteger('user_id');
            $table->string('NOTIFICATION_TYPE');
            $table->string('NOTIF_TITLE')->nullable();
            $table->text('NOTIF_MSG')->nullable();
            $table->string('NOTIFIABLE_TYPE')->nullable();
            $table->unsignedBigInteger('NOTIFIABLE_ID')->nullable();
            $table->text('NOTIFICATION_DATA')->nullable();
            $table->timestamp('READ_AT')->nullable();
            $table->unsignedBigInteger('REF_NOTIF_CREATED_BY')->nullable();
            $table->unsignedBigInteger('REF_NOTIF_UPDATED_BY')->nullable();
            $table->timestamp('REF_NOTIF_CREATED_DT')->nullable();
            $table->timestamp('REF_NOTIF_UPDATED_DT')->nullable();

            $table->index(['NOTIFIABLE_TYPE', 'NOTIFIABLE_ID'], 'notif_master_notifiable_index');
            $table->foreign('user_id')->references('LGL_ROW_ID')->on('LGL_USER')->onDelete('cascade');
        });

        // 3. Cache
        Schema::create('LGL_CACHE', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('LGL_CACHE_LOCK', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // 4. Queues
        Schema::create('LGL_JOB_QUEUE', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('LGL_JOB_BATCH', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('LGL_FAILED_JOB', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // 5. Sessions
        Schema::create('LGL_SESSION', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // 6. Password Resets
        Schema::create('LGL_PASSWORD_RESET', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('LGL_PASSWORD_RESET');
        Schema::dropIfExists('LGL_SESSION');
        Schema::dropIfExists('LGL_FAILED_JOB');
        Schema::dropIfExists('LGL_JOB_BATCH');
        Schema::dropIfExists('LGL_JOB_QUEUE');
        Schema::dropIfExists('LGL_CACHE_LOCK');
        Schema::dropIfExists('LGL_CACHE');
        Schema::dropIfExists('LGL_NOTIFICATION_MASTER');
        Schema::dropIfExists('LGL_USER_ADTRL_LOG');
    }
};
