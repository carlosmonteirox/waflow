<?php

namespace App\Http\Controllers;

use App\Services\OpenWaClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(OpenWaClient $openWa): View
    {
        $databaseOnline = true;

        try {
            DB::select('select 1');
        } catch (Throwable $exception) {
            report($exception);
            $databaseOnline = false;
        }

        return view('dashboard', [
            'openWa' => $openWa->health(),
            'databaseOnline' => $databaseOnline,
            'redisConfigured' => config('cache.default') === 'redis',
            'metrics' => [
                'instances' => 0,
                'connected' => 0,
                'messages' => 0,
                'failures' => 0,
            ],
        ]);
    }
}
