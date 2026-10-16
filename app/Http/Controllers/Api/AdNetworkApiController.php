<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdConfig;
use Illuminate\Http\JsonResponse;

class AdNetworkApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => AdConfig::payload(),
        ]);
    }
}
