<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $t) {
                if (!Schema::hasColumn('tasks', 'is_daily')) {
                    $t->boolean('is_daily')->default(true)->after('enabled');
                }
            });
        }

        if (Schema::hasTable('task_sessions')) {
            Schema::table('task_sessions', function (Blueprint $t) {
                if (!Schema::hasColumn('task_sessions', 'task_date_utc')) {
                    $t->date('task_date_utc')->nullable()->after('status')->index();
                }
            });

            try {
                Schema::table('task_sessions', function (Blueprint $t) {
                    $t->dropUnique('task_sessions_user_id_task_id_unique');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('task_sessions', function (Blueprint $t) {
                    $t->dropUnique(['user_id', 'task_id']);
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('task_sessions', function (Blueprint $t) {
                    $t->unique(['user_id', 'task_id', 'task_date_utc'], 'task_session_daily_unique');
                });
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'is_daily')) {
            Schema::table('tasks', function (Blueprint $t) {
                $t->dropColumn('is_daily');
            });
        }

        if (Schema::hasTable('task_sessions')) {
            try {
                Schema::table('task_sessions', function (Blueprint $t) {
                    $t->dropUnique('task_session_daily_unique');
                });
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('task_sessions', 'task_date_utc')) {
                Schema::table('task_sessions', function (Blueprint $t) {
                    $t->dropColumn('task_date_utc');
                });
            }
        }
    }
};
