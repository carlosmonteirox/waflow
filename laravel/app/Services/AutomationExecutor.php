<?php

namespace App\Services;

use App\Jobs\ExecuteAutomationFlow;
use App\Models\FlowExecution;
use App\Models\Workspace;
use Illuminate\Database\QueryException;

class AutomationExecutor
{
    public function dispatch(Workspace $workspace, string $triggerType, array $payload, string $sourceKey): int
    {
        $created = 0;
        $flows = $workspace->flows()->where('status', 'active')->where('trigger_type', $triggerType)->get();

        foreach ($flows as $flow) {
            try {
                $execution = FlowExecution::query()->create([
                    'workspace_id' => $workspace->id,
                    'flow_id' => $flow->id,
                    'source_key' => $sourceKey,
                    'trigger_type' => $triggerType,
                    'status' => 'queued',
                    'trigger_payload' => $payload,
                ]);
            } catch (QueryException $exception) {
                if (FlowExecution::query()->where('flow_id', $flow->id)->where('source_key', $sourceKey)->exists()) {
                    continue;
                }

                throw $exception;
            }
            ExecuteAutomationFlow::dispatch($execution->id);
            $created++;
        }

        return $created;
    }
}
