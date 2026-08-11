<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenWaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemHealthController extends Controller
{
    public function __invoke(OpenWaClient $openWa): JsonResponse
    {
        $database = $this->databaseHealth();
        $openWaHealth = $openWa->health();
        $ready = $database['ok'] && $openWaHealth['ok'];

        return response()->json([
            'status' => $ready ? 'ok' : 'degraded',
            'service' => 'waflow',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'database' => $database,
                'openwa' => $openWaHealth,
            ],
        ], $ready ? 200 : 503);
    }

    /**
     * @return array{ok: bool, status: string}
     */
    private function databaseHealth(): array
    {
        try {
            DB::select('select 1');

            return ['ok' => true, 'status' => 'online'];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'status' => 'offline'];
        }
    }
}
