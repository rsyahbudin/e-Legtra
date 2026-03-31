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
        // Fix LGL_PERMISSION
        Schema::table('LGL_PERMISSION', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_PERMISSION', 'slug') && ! Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_CODE')) {
                $table->renameColumn('slug', 'PERMISSION_CODE');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'group') && ! Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_GROUP')) {
                $table->renameColumn('group', 'PERMISSION_GROUP');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'description') && ! Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_DESC')) {
                $table->renameColumn('description', 'PERMISSION_DESC');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'is_active') && ! Schema::hasColumn('LGL_PERMISSION', 'IS_ACTIVE')) {
                $table->renameColumn('is_active', 'IS_ACTIVE');
            }
        });

        // Fix LGL_DIVISION
        Schema::table('LGL_DIVISION', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_DIVISION', 'division_id') && ! Schema::hasColumn('LGL_DIVISION', 'REF_DIV_ID')) {
                $table->renameColumn('division_id', 'REF_DIV_ID');
            }
            if (Schema::hasColumn('LGL_DIVISION', 'description') && ! Schema::hasColumn('LGL_DIVISION', 'REF_DIV_DESC')) {
                $table->renameColumn('description', 'REF_DIV_DESC');
            }
            if (Schema::hasColumn('LGL_DIVISION', 'is_active') && ! Schema::hasColumn('LGL_DIVISION', 'IS_ACTIVE')) {
                $table->renameColumn('is_active', 'IS_ACTIVE');
            }
        });

        // Fix LGL_DEPARTMENT
        Schema::table('LGL_DEPARTMENT', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_DEPARTMENT', 'division_id')) {
                $table->renameColumn('division_id', 'DIV_ID');
            }
        });

        // Fix LGL_USER
        Schema::table('LGL_USER', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_USER', 'USER_ID') && ! Schema::hasColumn('LGL_USER', 'USER_NAME')) {
                $table->renameColumn('USER_ID', 'USER_NAME');
                $table->string('USER_NAME', 100)->nullable()->change();
            } elseif (! Schema::hasColumn('LGL_USER', 'USER_NAME')) {
                $table->string('USER_NAME', 100)->nullable()->after('USER_FULLNAME');
            }

            if (Schema::hasColumn('LGL_USER', 'division_id') && ! Schema::hasColumn('LGL_USER', 'DIV_ID')) {
                $table->renameColumn('division_id', 'DIV_ID');
            }
            if (Schema::hasColumn('LGL_USER', 'department_id') && ! Schema::hasColumn('LGL_USER', 'DEPT_ID')) {
                $table->renameColumn('department_id', 'DEPT_ID');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('LGL_PERMISSION', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_CODE')) {
                $table->renameColumn('PERMISSION_CODE', 'slug');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_GROUP')) {
                $table->renameColumn('PERMISSION_GROUP', 'group');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'PERMISSION_DESC')) {
                $table->renameColumn('PERMISSION_DESC', 'description');
            }
            if (Schema::hasColumn('LGL_PERMISSION', 'IS_ACTIVE')) {
                $table->renameColumn('IS_ACTIVE', 'is_active');
            }
        });

        Schema::table('LGL_DIVISION', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_DIVISION', 'REF_DIV_ID')) {
                $table->renameColumn('REF_DIV_ID', 'division_id');
            }
            if (Schema::hasColumn('LGL_DIVISION', 'REF_DIV_DESC')) {
                $table->renameColumn('REF_DIV_DESC', 'description');
            }
            if (Schema::hasColumn('LGL_DIVISION', 'IS_ACTIVE')) {
                $table->renameColumn('IS_ACTIVE', 'is_active');
            }
        });

        Schema::table('LGL_DEPARTMENT', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_DEPARTMENT', 'DIV_ID')) {
                $table->renameColumn('DIV_ID', 'division_id');
            }
        });

        Schema::table('LGL_USER', function (Blueprint $table) {
            if (Schema::hasColumn('LGL_USER', 'USER_NAME')) {
                $table->renameColumn('USER_NAME', 'USER_ID');
                $table->string('USER_ID', 10)->nullable()->change();
            }
            if (Schema::hasColumn('LGL_USER', 'DIV_ID')) {
                $table->renameColumn('DIV_ID', 'division_id');
            }
            if (Schema::hasColumn('LGL_USER', 'DEPT_ID')) {
                $table->renameColumn('DEPT_ID', 'department_id');
            }
        });
    }
};
