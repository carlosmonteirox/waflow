<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\InterWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InterWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, string $event, InterWebhookService $webhooks): JsonResponse
    {
        $expected = (string) config('billing.inter.webhook_secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        abort_unless($request->isJson(), 415, 'O webhook deve usar application/json.');
        $payload = $request->json()->all();
        $keys = $event === 'rec' ? ['recs', 'recorrencias', 'rec'] : ['cobrs', 'cobrancas', 'cobr'];
        $items = null;
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $items = array_is_list($payload[$key]) ? $payload[$key] : [$payload[$key]];
                break;
            }
        }
        if ($items === null && (($event === 'rec' && isset($payload['idRec'])) || ($event === 'cobr' && (isset($payload['txid']) || isset($payload['txId']))))) {
            $items = [$payload];
        }

        $rules = [
            'items' => ['required', 'array', 'max:100'],
            'items.*' => ['required', 'array'],
            'items.*.status' => ['required', 'string', 'max:40'],
        ];
        if ($event === 'rec') {
            $rules['items.*.idRec'] = ['required', 'string', 'regex:/^[A-Za-z0-9]{29}$/'];
        } else {
            $rules['items.*.txid'] = ['required_without:items.*.txId', 'string', 'regex:/^[A-Za-z0-9]{26,35}$/'];
            $rules['items.*.txId'] = ['required_without:items.*.txid', 'string', 'regex:/^[A-Za-z0-9]{26,35}$/'];
            $rules['items.*.idRec'] = ['nullable', 'string', 'regex:/^[A-Za-z0-9]{29}$/'];
        }
        Validator::make(['items' => $items], $rules)->validate();

        $processed = $webhooks->handle($event, $payload);

        return response()->json(['received' => true, 'processed' => $processed]);
    }
}
