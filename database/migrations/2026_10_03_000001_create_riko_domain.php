<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('wallet_id', 12)->nullable()->unique();
            $t->string('country', 2)->default('IN');
            $t->string('role')->default('user');
            $t->string('kyc_status')->default('not_started');
            $t->timestamp('kyc_expires_at')->nullable();
            $t->foreignId('referrer_id')->nullable()->constrained('users');
            $t->json('referral_policy')->nullable();
            $t->json('restrictions')->nullable();
            $t->timestamp('last_active_at')->nullable();
            $t->timestamp('last_reminded_at')->nullable();
            $t->boolean('push_consent')->default(false);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('hash', 64)->unique();
            $t->string('channel')->default('app');
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('owner');
            $t->string('asset', 8);
            $t->decimal('balance', 28, 8)->default(0);
            $t->boolean('allow_negative')->default(false);
            $t->timestamps();
            $t->unique(['owner', 'asset']);
        });
        Schema::create('operations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('key', 191)->unique();
            $t->string('fingerprint', 64);
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('type');
            $t->string('status')->default('completed');
            $t->string('asset', 8);
            $t->decimal('amount', 28, 8);
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['type', 'status', 'created_at']);
        });
        Schema::create('entries', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('operation_id')->constrained('operations');
            $t->foreignId('account_id')->constrained('accounts');
            $t->decimal('amount', 28, 8);
            $t->timestamp('created_at');
            $t->unique(['operation_id', 'account_id']);
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('direction');
            $t->json('countries');
            $t->json('assets');
            $t->boolean('enabled')->default(false);
            $t->boolean('provider_ready')->default(false);
            $t->text('instructions')->nullable();
            $t->decimal('fee', 20, 8)->default(0);
            $t->decimal('minimum', 20, 8)->default(1);
            $t->decimal('maximum', 20, 8)->default(10000);
            $t->timestamps();
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category');
            $t->text('instructions');
            $t->string('package_name')->nullable();
            $t->string('url')->nullable();
            $t->json('countries');
            $t->json('devices');
            $t->json('channels');
            $t->json('rates')->nullable();
            $t->decimal('reward', 20, 8);
            $t->decimal('budget', 20, 8);
            $t->boolean('enabled')->default(false);
            $t->boolean('provider_ready')->default(false);
            $t->timestamps();
        });
        Schema::create('task_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('task_id')->constrained();
            $t->string('code_hash', 64)->unique();
            $t->string('channel');
            $t->string('device');
            $t->decimal('reward', 20, 8);
            $t->json('policy');
            $t->string('status')->default('started');
            $t->string('event_id')->nullable()->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['user_id', 'task_id']);
        });
        Schema::create('referral_rewards', function (Blueprint $t) {
            $t->id();
            $t->string('source')->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('referred_id')->constrained('users');
            $t->decimal('amount', 20, 8);
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('records', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->string('kind');
            $t->string('status')->default('pending');
            $t->string('asset', 8)->nullable();
            $t->decimal('amount', 28, 8)->nullable();
            $t->json('data');
            $t->timestamps();
            $t->index(['user_id', 'kind', 'created_at']);
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('sender_id')->constrained('users');
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('target')->nullable();
            $t->json('data')->nullable();
            $t->timestamp('created_at');
        });
        Schema::create('daily_stats', function (Blueprint $t) {
            $t->date('day')->primary();
            $t->json('data');
            $t->timestamp('updated_at');
        });
        Schema::create('contest_seasons', function (Blueprint $t) {
            $t->id();
            $t->string('month', 7)->unique();
            $t->string('status')->default('draft');
            $t->json('prizes');
            $t->decimal('pool', 20, 8);
            $t->timestamps();
        });
        Schema::create('contest_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('season_id')->constrained('contest_seasons');
            $t->foreignId('user_id')->constrained();
            $t->unsignedInteger('rank');
            $t->decimal('score', 20, 8);
            $t->decimal('prize', 20, 8);
            $t->string('status')->default('pending');
            $t->timestamps();
            $t->unique(['season_id', 'user_id']);
            $t->unique(['season_id', 'rank']);
        });
    }

    public function down(): void
    {
        foreach (['contest_results', 'contest_seasons', 'daily_stats', 'audit_logs', 'support_messages', 'records', 'referral_rewards', 'task_sessions', 'tasks', 'payment_methods', 'entries', 'operations', 'accounts', 'api_tokens', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('referrer_id');
            $t->dropColumn(['wallet_id', 'country', 'role', 'kyc_status', 'kyc_expires_at', 'referral_policy', 'restrictions', 'last_active_at', 'last_reminded_at', 'push_consent']);
        });
    }
};
