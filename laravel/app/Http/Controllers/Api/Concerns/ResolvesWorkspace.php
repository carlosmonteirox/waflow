<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesWorkspace
{
    protected function workspace(Request $request): Workspace
    {
        return $request->attributes->get('waflow.workspace');
    }

    protected function ensureWorkspace(Model $model, Workspace $workspace): void
    {
        abort_unless((int) $model->getAttribute('workspace_id') === (int) $workspace->id, 404);
    }
}
