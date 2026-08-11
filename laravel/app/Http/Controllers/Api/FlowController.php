<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Flow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlowController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace($request)->flows()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'steps' => ['nullable', 'array'],
        ]);

        $flow = $this->workspace($request)->flows()->create([
            'name' => $attributes['name'],
            'trigger_type' => $attributes['trigger_type'],
            'steps' => $attributes['steps'] ?? [],
            'status' => 'draft',
        ]);

        return response()->json(['data' => $flow], 201);
    }

    public function show(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));

        return response()->json(['data' => $flow]);
    }

    public function update(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $attributes = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'trigger_type' => ['sometimes', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'steps' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'paused'])],
        ]);

        $flow->update($attributes);

        return response()->json(['data' => $flow->fresh()]);
    }

    public function destroy(Request $request, Flow $flow): JsonResponse
    {
        $this->ensureWorkspace($flow, $this->workspace($request));
        $flow->delete();

        return response()->json(status: 204);
    }
}
