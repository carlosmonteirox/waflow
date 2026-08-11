<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessage;
use App\Models\MessageEvent;
use App\Services\UsageLimiter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', Rule::in(['inbound', 'outbound'])],
            'instance_uuid' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->messageEvents()->with(['instance', 'contact'])->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['direction'] ?? null), fn ($query) => $query->where('direction', $attributes['direction']));
        $query->when(filled($attributes['instance_uuid'] ?? null), fn ($query) => $query->whereHas('instance', fn ($instances) => $instances->where('uuid', $attributes['instance_uuid'])));
        $query->when(filled($attributes['search'] ?? null), function ($query) use ($attributes): void {
            $search = '%'.$attributes['search'].'%';
            $query->where(fn ($query) => $query->where('to', 'like', $search)->orWhere('from', 'like', $search));
        });
        $query->when(filled($attributes['date_from'] ?? null), fn ($query) => $query->where('created_at', '>=', $attributes['date_from']));
        $query->when(filled($attributes['date_to'] ?? null), fn ($query) => $query->where('created_at', '<=', $attributes['date_to']));
        if (($attributes['sort'] ?? 'newest') === 'oldest') {
            $query->reorder('created_at');
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function store(Request $request, UsageLimiter $limiter): JsonResponse
    {
        $workspace = $this->workspace($request);
        $attributes = $this->validateMessage($request);
        $idempotencyKey = $request->header('Idempotency-Key') ?: ($attributes['idempotency_key'] ?? null);

        $result = $limiter->serialized($workspace, function () use ($workspace, $attributes, $idempotencyKey, $limiter): array {
            if ($idempotencyKey) {
                $existing = $workspace->messageEvents()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return ['message' => $existing, 'replay' => true];
                }
            }

            $limiter->ensureMessages($workspace);
            $instance = $workspace->instances()->where('uuid', $attributes['instance_uuid'])->firstOrFail();
            abort_unless($instance->status === 'connected', 409, 'A instância do WhatsApp não está conectada.');

            return [
                'message' => $workspace->messageEvents()->create([
                    'whatsapp_instance_id' => $instance->id,
                    'direction' => 'outbound',
                    'status' => 'queued',
                    'to' => $attributes['to'],
                    'idempotency_key' => $idempotencyKey,
                    'payload' => $this->payload($attributes),
                ]),
                'replay' => false,
            ];
        });

        if ($result['replay']) {
            return response()->json(['data' => $result['message'], 'idempotent_replay' => true]);
        }

        SendWhatsAppMessage::dispatch($result['message']->id);

        return response()->json(['data' => $result['message'], 'message' => 'Mensagem aceita na fila Waflow.'], 202);
    }

    public function bulk(Request $request, UsageLimiter $limiter): JsonResponse
    {
        $attributes = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:100'],
            'messages.*' => ['required', 'array'],
        ]);
        $workspace = $this->workspace($request);
        $validated = [];
        foreach ($attributes['messages'] as $item) {
            $child = Request::create($request->path(), 'POST', $item);
            $validated[] = $this->validateMessage($child);
        }

        $result = $limiter->serialized($workspace, function () use ($workspace, $validated, $limiter): array {
            $pendingKeys = [];
            $newCount = 0;
            foreach ($validated as $item) {
                $key = $item['idempotency_key'] ?? null;
                if ($key && (isset($pendingKeys[$key]) || $workspace->messageEvents()->where('idempotency_key', $key)->exists())) {
                    continue;
                }
                if ($key) {
                    $pendingKeys[$key] = true;
                }
                $newCount++;
            }

            $limiter->ensureMessages($workspace, $newCount);
            $messages = [];
            $createdIds = [];
            foreach ($validated as $item) {
                $key = $item['idempotency_key'] ?? null;
                $message = $key ? $workspace->messageEvents()->where('idempotency_key', $key)->first() : null;
                if (! $message) {
                    $instance = $workspace->instances()->where('uuid', $item['instance_uuid'])->firstOrFail();
                    abort_unless($instance->status === 'connected', 409, 'A instância do WhatsApp não está conectada.');
                    $message = $workspace->messageEvents()->create([
                        'whatsapp_instance_id' => $instance->id,
                        'direction' => 'outbound',
                        'status' => 'queued',
                        'to' => $item['to'],
                        'idempotency_key' => $key,
                        'payload' => $this->payload($item),
                    ]);
                    $createdIds[] = $message->id;
                }
                $messages[] = $message;
            }

            return compact('messages', 'createdIds');
        });

        foreach ($result['createdIds'] as $messageId) {
            SendWhatsAppMessage::dispatch($messageId);
        }

        return response()->json(['data' => $result['messages']], 202);
    }

    public function show(Request $request, MessageEvent $message): JsonResponse
    {
        $this->ensureWorkspace($message, $this->workspace($request));

        return response()->json(['data' => $message->load(['instance', 'contact'])]);
    }

    private function validateMessage(Request $request): array
    {
        $attributes = $request->validate([
            'instance_uuid' => ['required', 'uuid'],
            'to' => ['required', 'string', 'max:128'],
            'type' => ['required', Rule::in(['text', 'image', 'video', 'audio', 'document', 'sticker', 'location', 'contact', 'poll'])],
            'text' => ['nullable', 'string', 'max:4096'],
            'body' => ['nullable', 'string', 'max:4096'],
            'url' => ['nullable', 'url:http,https', 'max:4096'],
            'base64' => ['nullable', 'string'],
            'mimetype' => ['nullable', 'string', 'max:120'],
            'filename' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'mentions' => ['nullable', 'array', 'max:100'],
            'mentions.*' => ['string', 'max:64'],
            'ptt' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1024'],
            'address' => ['nullable', 'string', 'max:1024'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'array', 'min:2', 'max:12'],
            'options.*' => ['string', 'max:100'],
            'allow_multiple_answers' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
        ]);

        $missing = match ($attributes['type']) {
            'text' => ! filled($attributes['text'] ?? $attributes['body'] ?? null),
            'image', 'video', 'audio', 'document', 'sticker' => ! filled($attributes['url'] ?? null) && ! filled($attributes['base64'] ?? null),
            'location' => ! isset($attributes['latitude'], $attributes['longitude']),
            'contact' => ! filled($attributes['contact_name'] ?? null) || ! filled($attributes['contact_number'] ?? null),
            'poll' => ! filled($attributes['name'] ?? null) || count($attributes['options'] ?? []) < 2,
            default => false,
        };
        if ($missing) {
            throw ValidationException::withMessages(['type' => 'O conteúdo obrigatório para este tipo de mensagem não foi informado.']);
        }

        return $attributes;
    }

    private function payload(array $attributes): array
    {
        $payload = Arr::except($attributes, ['instance_uuid', 'to', 'idempotency_key', 'body']);
        if ($attributes['type'] === 'text') {
            $payload['text'] = $attributes['text'] ?? $attributes['body'];
        }

        return $payload;
    }
}
