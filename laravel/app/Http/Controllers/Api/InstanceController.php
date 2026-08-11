<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppInstance;
use App\Services\InstanceManager;
use App\Services\OpenWaClient;
use App\Services\UsageLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstanceController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace($request)->instances()->latest()->get()]);
    }

    public function store(Request $request, UsageLimiter $limiter, InstanceManager $manager): JsonResponse
    {
        $workspace = $this->workspace($request);
        $limiter->ensureInstances($workspace);
        $attributes = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $instance = $workspace->instances()->create([
            'name' => $attributes['name'],
            'session_name' => Str::slug($workspace->slug.' '.$attributes['name']).'-'.Str::lower(Str::random(8)),
            'status' => 'provisioning',
        ]);

        return response()->json(['data' => $manager->provision($instance)], 201);
    }

    public function show(Request $request, WhatsAppInstance $instance): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));

        return response()->json(['data' => $instance]);
    }

    public function update(Request $request, WhatsAppInstance $instance): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $instance->update($request->validate(['name' => ['sometimes', 'string', 'max:120']]));

        return response()->json(['data' => $instance->fresh()]);
    }

    public function destroy(Request $request, WhatsAppInstance $instance, InstanceManager $manager): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $manager->delete($instance);

        return response()->json(status: 204);
    }

    public function connect(Request $request, WhatsAppInstance $instance, InstanceManager $manager): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $result = $manager->connect($instance);

        return response()->json(['data' => $result['instance'], 'qr_code' => $result['qr_code']]);
    }

    public function disconnect(Request $request, WhatsAppInstance $instance, InstanceManager $manager): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));

        return response()->json(['data' => $manager->disconnect($instance)]);
    }

    public function status(Request $request, WhatsAppInstance $instance, InstanceManager $manager): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));

        return response()->json(['data' => $manager->refresh($instance)]);
    }

    public function qr(Request $request, WhatsAppInstance $instance, OpenWaClient $openwa): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        abort_unless($instance->openwa_session_id, 409, 'A instância ainda não foi provisionada.');

        return response()->json(['data' => $openwa->qrCode($instance->openwa_session_id)]);
    }

    public function groups(Request $request, WhatsAppInstance $instance, OpenWaClient $openwa): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $this->ensureConnected($instance);

        return response()->json(['data' => $openwa->groups($instance->openwa_session_id)]);
    }

    public function channels(Request $request, WhatsAppInstance $instance, OpenWaClient $openwa): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $this->ensureConnected($instance);

        return response()->json(['data' => $openwa->channels($instance->openwa_session_id)]);
    }

    public function contacts(Request $request, WhatsAppInstance $instance, OpenWaClient $openwa): JsonResponse
    {
        $this->ensureWorkspace($instance, $this->workspace($request));
        $this->ensureConnected($instance);

        return response()->json(['data' => $openwa->contacts($instance->openwa_session_id)]);
    }

    private function ensureConnected(WhatsAppInstance $instance): void
    {
        abort_unless($instance->status === 'connected' && $instance->openwa_session_id, 409, 'A instância não está conectada.');
    }
}
