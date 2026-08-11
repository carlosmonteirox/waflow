<?php

namespace App\Services;

use App\Jobs\ExecuteAutomationFlow;
use App\Models\FlowExecution;
use App\Models\Workspace;
use Illuminate\Support\Str;

class AutomationExecutor
{
    public function dispatch(Workspace $workspace, string $triggerType, array $payload, string $sourceKey): int
    {
        $created = 0;
        $flows = $workspace->flows()->where('status', 'active')->where('trigger_type', $triggerType)->get();

        foreach ($flows as $flow) {
            $inserted = FlowExecution::query()->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'flow_id' => $flow->id,
                'source_key' => $sourceKey,
                'trigger_type' => $triggerType,
                'status' => 'queued',
                'trigger_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                continue;
            }
            $execution = FlowExecution::query()->where('flow_id', $flow->id)->where('source_key', $sourceKey)->firstOrFail();
            ExecuteAutomationFlow::dispatch($execution->id);
            $created++;
        }

        return $created;
    }
}
