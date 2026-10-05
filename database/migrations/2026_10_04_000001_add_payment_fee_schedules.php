<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $t) {
            $t->decimal('processing_percent', 8, 4)->nullable();
            $t->decimal('fee_tax_percent', 8, 4)->default(0);
            $t->decimal('platform_percent', 8, 4)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', fn (Blueprint $t) => $t->dropColumn(['processing_percent', 'fee_tax_percent', 'platform_percent']));
    }
};
