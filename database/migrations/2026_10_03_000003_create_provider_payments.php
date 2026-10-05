<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $t) {
            $t->string('provider', 50)->nullable();
            $t->string('network', 50)->nullable();
        });
        Schema::create('provider_payments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('method_id')->constrained('payment_methods');
            $t->uuid('request_key');
            $t->string('fingerprint', 64);
            $t->string('provider', 50);
            $t->string('direction', 12);
            $t->string('asset', 8);
            $t->string('network', 50)->nullable();
            $t->decimal('amount', 28, 8);
            $t->decimal('fee', 28, 8);
            $t->text('destination')->nullable();
            $t->string('status', 40)->default('queued')->index();
            $t->string('external_id')->nullable();
            $t->text('instructions')->nullable();
            $t->string('error_code', 50)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'request_key']);
            $t->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payments');
        Schema::table('payment_methods', fn (Blueprint $t) => $t->dropColumn(['provider', 'network']));
    }
};
