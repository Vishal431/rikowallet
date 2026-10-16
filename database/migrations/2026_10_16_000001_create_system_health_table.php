<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->json('details');
            $table->timestamp('observed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health');
    }
};
