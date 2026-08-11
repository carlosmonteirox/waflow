<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Services\AutomationDefinition;
use App\Services\AutomationExecutor;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FlowController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused'])],
            'trigger_type' => ['nullable', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->flows()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['trigger_type'] ?? null), fn ($query) => $query->where('trigger_type', $attributes['trigger_type']));
        $query->when(filled($attributes['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$attributes['search'].'%'));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function store(Request $request, AutomationDefinition $definition): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'steps' => ['nullable', 'array', 'max:50'],
        ]);

        $flow = $this->workspace($request)->flows()->create([
            'name' => $attributes['name'],
            'trigger_type' => $attributes['trigger_type'],
            'steps' => $definition->validate($attributes['steps'] ?? []),
            'status' => 'draft',
        ]);

        return response()->json(['data' => $flow], 201);
    }

    public function show(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));

        return response()->json(['data' => $flow]);
    }

    public function update(Request $request, Flow $flow, AutomationDefinition $definition): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $attributes = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'trigger_type' => ['sometimes', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'steps' => ['sometimes', 'array', 'max:50'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused'])],
        ]);

        if (array_key_exists('steps', $attributes)) {
            $attributes['steps'] = $definition->validate($attributes['steps']);
        }
        $flow->update($attributes);

        return response()->json(['data' => $flow->fresh()]);
    }

    public function destroy(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $flow->delete();

        return response()->json(status: 204);
    }

    public function executions(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['queued', 'running', 'retrying', 'completed', 'skipped', 'failed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $flow->executions()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function test(Request $request, Flow $flow, AutomationExecutor $executor): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $payload = $request->validate(['payload' => ['nullable', 'array']])['payload'] ?? [];
        $originalStatus = $flow->status;
        $flow->update(['status' => 'active']);
        try {
            $created = $executor->dispatch($flow->workspace, $flow->trigger_type, $payload, 'manual:'.(string) Str::uuid());
        } finally {
            $flow->update(['status' => $originalStatus]);
        }

        return response()->json(['data' => ['queued_executions' => $created]], 202);
    }
}
