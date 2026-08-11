<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\WorkspaceContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $context = app(WorkspaceContext::class);

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@waflow.local'],
            [
                'name' => 'Waflow Admin',
                'password' => 'password123',
                'email_verified_at' => now(),
            ],
        );

        $workspace = $user->workspaces()->wherePivot('status', 'active')->first()
            ?: $context->createForUser($user, 'Waflow Demo');

        $workspace->instances()->firstOrCreate(
            ['session_name' => 'waflow-demo-atendimento'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Atendimento',
                'phone_number' => '+55 62 99999-9999',
                'status' => 'disconnected',
            ],
        );
    }
}
