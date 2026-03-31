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
        Schema::table('LGL_NOTIFICATION_MASTER', function (Blueprint $table) {
            // Fix NOTIFIABLE_ID
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'notifiable_id')) {
                $table->renameColumn('notifiable_id', 'NOTIFIABLE_ID');
            } elseif (! Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIFIABLE_ID')) {
                $table->unsignedBigInteger('NOTIFIABLE_ID')->nullable()->after('NOTIFIABLE_TYPE');
            }

            // Fix REF_NOTIF_TITLE (was title)
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'title')) {
                $table->renameColumn('title', 'NOTIF_TITLE');
            } elseif (! Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIF_TITLE')) {
                $table->string('NOTIF_TITLE')->after('NOTIFICATION_TYPE');
            }

            // Fix REF_NOTIF_MSG (was message)
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'message')) {
                $table->renameColumn('message', 'NOTIF_MSG');
            } elseif (! Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIF_MSG')) {
                $table->text('NOTIF_MSG')->after('NOTIF_TITLE');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_NOTIFICATION_MASTER', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIFIABLE_ID')) {
                // Open question on rollback: keep as is or revert?
                // Usually revert to previous state, but "previous" state is ambiguous here.
                // We will rename back to 'notifiable_id' if we assume that was the "original" intention before this fix.
                $table->renameColumn('NOTIFIABLE_ID', 'notifiable_id');
            }
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIF_TITLE')) {
                $table->renameColumn('NOTIF_TITLE', 'title');
            }
            if (Schema::hasColumn('LGL_NOTIFICATION_MASTER', 'NOTIF_MSG')) {
                $table->renameColumn('NOTIF_MSG', 'message');
            }
        });
    }
};
