<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_networks')) {
            Schema::create('ad_networks', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('name', 64);
                $table->string('type', 32)->default('admob');
                $table->boolean('is_enabled')->default(0);
                $table->unsignedInteger('weight')->default(0);
                $table->unsignedInteger('sort_order')->default(0);

                // IDs
                $table->string('app_id_android')->nullable();
                $table->string('app_id_ios')->nullable();
                $table->string('banner_android')->nullable();
                $table->string('banner_ios')->nullable();
                $table->string('banner_2_android')->nullable();
                $table->string('banner_2_ios')->nullable();
                $table->string('native_android')->nullable();
                $table->string('native_ios')->nullable();
                $table->string('native_2_android')->nullable();
                $table->string('native_2_ios')->nullable();
                $table->string('interstitial_android')->nullable();
                $table->string('interstitial_ios')->nullable();
                $table->string('rewarded_android')->nullable();
                $table->string('rewarded_ios')->nullable();
                $table->string('app_open_android')->nullable();
                $table->string('app_open_ios')->nullable();

                $table->timestamps();
            });

            $defaults = [
                ['code' => 'admob', 'name' => 'Google AdMob', 'is_enabled' => 1, 'weight' => 100, 'sort_order' => 1],
                ['code' => 'applovin', 'name' => 'AppLovin MAX', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 2],
                ['code' => 'unity', 'name' => 'Unity Ads', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 3],
                ['code' => 'mintegral', 'name' => 'Mintegral', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 4],
                ['code' => 'yandex', 'name' => 'Yandex Ads', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 5],
                ['code' => 'meta', 'name' => 'Meta Audience Network', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 6],
            ];

            foreach ($defaults as $row) {
                DB::table('ad_networks')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_networks');
    }
};
