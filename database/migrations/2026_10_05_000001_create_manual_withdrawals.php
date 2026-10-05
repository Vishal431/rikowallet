<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_currencies', function (Blueprint $t) {
            $t->string('code', 12)->primary();
            $t->unsignedTinyInteger('precision')->default(2);
            $t->decimal('per_rc', 28, 12);
            $t->timestamps();
        });
        Schema::table('payment_methods', function (Blueprint $t) {
            $t->boolean('manual')->default(false);
            $t->string('category')->default('Wallet');
            $t->string('payout_currency', 12)->nullable();
            $t->string('image_file')->nullable();
            $t->string('fee_mode')->default('flat');
            $t->decimal('fee_value', 20, 8)->default(0);
            $t->decimal('fee_cap', 20, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', fn (Blueprint $t) => $t->dropColumn(['manual', 'category', 'payout_currency', 'image_file', 'fee_mode', 'fee_value', 'fee_cap']));
        Schema::dropIfExists('payout_currencies');
    }
};
