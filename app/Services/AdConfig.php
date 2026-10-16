<?php

namespace App\Services;

use App\Models\AdNetwork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdConfig
{
    public const STRATEGY_MEDIATION = 'mediation';
    public const STRATEGY_ROTATION = 'rotation';
    public const STRATEGY_WATERFALL = 'waterfall';

    public static function ensureNetworksExist(): void
    {
        try {
            if (!Schema::hasTable('ad_networks')) {
                return;
            }

            $defaults = [
                ['code' => 'admob', 'name' => 'Google AdMob', 'is_enabled' => 1, 'weight' => 100, 'sort_order' => 1],
                ['code' => 'applovin', 'name' => 'AppLovin MAX', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 2],
                ['code' => 'unity', 'name' => 'Unity Ads', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 3],
                ['code' => 'mintegral', 'name' => 'Mintegral', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 4],
                ['code' => 'yandex', 'name' => 'Yandex Ads', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 5],
                ['code' => 'meta', 'name' => 'Meta Audience Network', 'is_enabled' => 0, 'weight' => 0, 'sort_order' => 6],
            ];

            foreach ($defaults as $row) {
                if (!AdNetwork::where('code', $row['code'])->exists()) {
                    AdNetwork::create($row);
                }
            }
        } catch (\Throwable $e) {}
    }

    public static function strategy(): string
    {
        try {
            $setting = DB::table('settings')->where('key', 'ad_strategy')->first();
            if ($setting) {
                $val = json_decode($setting->value, true);
                return is_string($val) ? $val : (is_array($val) ? ($val['strategy'] ?? 'waterfall') : 'waterfall');
            }
        } catch (\Throwable $e) {}

        return self::STRATEGY_WATERFALL;
    }

    public static function payload(): array
    {
        self::ensureNetworksExist();

        $strategy = self::strategy();

        $networks = collect();
        if (Schema::hasTable('ad_networks')) {
            $networks = AdNetwork::orderBy('sort_order')->get()->filter->isUsable()->values();
        }

        if (in_array($strategy, [self::STRATEGY_ROTATION, self::STRATEGY_WATERFALL]) && $networks->isEmpty()) {
            $strategy = self::STRATEGY_WATERFALL;
        }

        $priorityOrder = $networks->pluck('code')->all();

        return [
            'strategy' => $strategy,
            'priority_order' => $priorityOrder,
            'networks' => self::withNormalisedWeights($networks),
        ];
    }

    private static function withNormalisedWeights($networks): array
    {
        $count = $networks->count();
        if ($count === 0) {
            return [];
        }

        $total = $networks->sum('weight');
        $raw = $total > 0
            ? $networks->map(fn (AdNetwork $n) => $n->weight * 100 / $total)->all()
            : array_fill(0, $count, 100 / $count);

        $shares = self::largestRemainder($raw);

        return $networks->values()->map(function (AdNetwork $network, int $i) use ($shares) {
            $payload = $network->toPayload();
            $payload['weight'] = $shares[$i];
            return $payload;
        })->all();
    }

    private static function largestRemainder(array $raw): array
    {
        $floors = array_map('intval', $raw);
        $remaining = 100 - array_sum($floors);

        $order = array_keys($raw);
        usort($order, function ($a, $b) use ($raw, $floors) {
            return ($raw[$b] - $floors[$b]) <=> ($raw[$a] - $floors[$a]);
        });

        for ($i = 0; $i < $remaining; $i++) {
            $floors[$order[$i % count($order)]]++;
        }

        return $floors;
    }
}
