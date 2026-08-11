<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Plan::query()
                ->orderBy('monthly_price_cents')
                ->get(['name', 'slug', 'monthly_price_cents', 'instance_limit', 'message_limit', 'webhook_limit', 'api_key_limit', 'features']),
        ]);
    }
}
