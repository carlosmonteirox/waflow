<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\InterWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, string $event, InterWebhookService $webhooks): JsonResponse
    {
        $expected = (string) config('billing.inter.webhook_secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $processed = $webhooks->handle($event, $request->json()->all());

        return response()->json(['received' => true, 'processed' => $processed]);
    }
}
