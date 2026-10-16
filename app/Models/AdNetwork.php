<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdNetwork extends Model
{
    protected $table = 'ad_networks';

    protected $fillable = [
        'code',
        'type',
        'name',
        'is_enabled',
        'weight',
        'app_id_android',
        'app_id_ios',
        'banner_android',
        'banner_ios',
        'banner_2_android',
        'banner_2_ios',
        'native_android',
        'native_ios',
        'native_2_android',
        'native_2_ios',
        'interstitial_android',
        'interstitial_ios',
        'rewarded_android',
        'rewarded_ios',
        'app_open_android',
        'app_open_ios',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'weight' => 'integer',
        'sort_order' => 'integer',
    ];

    public function isUsable(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        $fields = [
            'banner_android', 'banner_2_android',
            'native_android', 'native_2_android',
            'interstitial_android', 'rewarded_android', 'app_open_android',
            'banner_ios', 'banner_2_ios',
            'native_ios', 'native_2_ios',
            'interstitial_ios', 'rewarded_ios', 'app_open_ios',
        ];

        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                return true;
            }
        }

        return false;
    }

    public function toPayload(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type ?: 'admob',
            'name' => $this->name,
            'priority' => (int) $this->sort_order,
            'weight' => $this->weight,
            'android' => [
                'app_id' => $this->app_id_android,
                'banner' => $this->banner_android,
                'banner_2' => $this->banner_2_android,
                'native' => $this->native_android,
                'native_2' => $this->native_2_android,
                'interstitial' => $this->interstitial_android,
                'rewarded' => $this->rewarded_android,
                'app_open' => $this->app_open_android,
            ],
            'ios' => [
                'app_id' => $this->app_id_ios,
                'banner' => $this->banner_ios,
                'banner_2' => $this->banner_2_ios,
                'native' => $this->native_ios,
                'native_2' => $this->native_2_ios,
                'interstitial' => $this->interstitial_ios,
                'rewarded' => $this->rewarded_ios,
                'app_open' => $this->app_open_ios,
            ],
        ];
    }
}
