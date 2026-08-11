<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Rules\PublicWebhookUrl;
use App\Services\UsageLimiter;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    use ResolvesWorkspace;

    private const EVENTS = ['*', 'message.received', 'message.sent', 'message.ack', 'message.failed', 'message.revoked', 'message.reaction', 'message.edited', 'session.status', 'session.qr', 'session.authenticated', 'session.disconnected'];

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace($request)->webhooks()->latest()->get()]);
    }

    public function store(Request $request, UsageLimiter $limiter): JsonResponse
    {
        $workspace = $this->workspace($request);
        $limiter->ensureWebhooks($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:2048', new PublicWebhookUrl],
            'events' => ['nullable', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(self::EVENTS)],
        ]);
        $secret = Str::random(64);
        $webhook = $workspace->webhooks()->create([
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'events' => $attributes['events'] ?? ['message.received'],
            'status' => 'active',
            'signing_secret' => $secret,
        ]);

        return response()->json(['data' => $webhook, 'signing_secret' => $secret], 201);
    }

    public function show(Request $request, Webhook $webhook): JsonResponse
    {
        $this->ensureWorkspace($webhook, $this->workspace($request));

        return response()->json(['data' => $webhook]);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $this->ensureWorkspace($webhook, $this->workspace($request));
        $webhook->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'url' => ['sometimes', 'url:http,https', 'max:2048', new PublicWebhookUrl],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(self::EVENTS)],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
        ]));

        return response()->json(['data' => $webhook->fresh()]);
    }

    public function test(Request $request, Webhook $webhook, WorkspaceWebhookDispatcher $dispatcher): JsonResponse
    {
        $workspace = $this->workspace($request);
        $this->ensureWorkspace($webhook, $workspace);
        $dispatcher->dispatchWebhook($webhook, 'test', ['message' => 'Teste de entrega do Waflow', 'webhook_uuid' => $webhook->uuid]);

        return response()->json(['message' => 'Entrega de teste adicionada à fila.'], 202);
    }

    public function destroy(Request $request, Webhook $webhook): JsonResponse
    {
        $this->ensureWorkspace($webhook, $this->workspace($request));
        $webhook->delete();

        return response()->json(status: 204);
    }
}
