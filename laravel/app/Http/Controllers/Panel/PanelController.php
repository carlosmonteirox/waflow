<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Rules\PublicWebhookUrl;
use App\Services\InstanceManager;
use App\Services\OpenWaClient;
use App\Services\UsageLimiter;
use App\Services\WorkspaceContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PanelController extends Controller
{
    private const WEBHOOK_EVENTS = [
        '*', 'message.received', 'message.sent', 'message.ack', 'message.failed',
        'message.revoked', 'message.reaction', 'message.edited', 'session.status',
        'session.qr', 'session.authenticated', 'session.disconnected',
    ];

    public function dashboard(Request $request, WorkspaceContext $context, OpenWaClient $openWa): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'dashboard', 'Visão geral', 'Acompanhe a disponibilidade da plataforma e a operação do WhatsApp.', [
            'metrics' => $this->metrics($workspace),
            'openWa' => $openWa->health(),
            'databaseOnline' => $this->databaseOnline(),
            'redisConfigured' => config('cache.default') === 'redis',
            'recentMessages' => $this->messageRows($workspace->messageEvents()->latest()->limit(6)->get()),
            'recentInstances' => $this->instanceRows($workspace->instances()->latest()->limit(5)->get()),
        ]);
    }

    public function instances(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'instances', 'Instâncias', 'Gerencie as sessões WhatsApp isoladas por workspace.', [
            'instances' => $this->instanceRows($workspace->instances()->latest()->get()),
        ]);
    }

    public function flows(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'flows', 'Fluxos', 'Automatize entradas, respostas e encaminhamentos de mensagens.', [
            'flows' => $this->flowRows($workspace->flows()->latest()->get()),
        ]);
    }

    public function messages(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());
        $messages = $workspace->messageEvents()->with(['instance', 'contact'])->latest()->paginate(12);

        return $this->render($request, $workspace, 'messages', 'Mensagens', 'Consulte eventos processados e mensagens enfileiradas.', [
            'messages' => $this->paginatedRows($messages, fn ($message): array => $this->messageRow($message)),
            'instances' => $this->instanceRows($workspace->instances()->latest()->get()),
        ]);
    }

    public function contacts(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());
        $contacts = $workspace->contacts()->latest()->paginate(12);

        return $this->render($request, $workspace, 'contacts', 'Contatos', 'Base de contatos capturados ou importados por API.', [
            'contacts' => $this->paginatedRows($contacts, fn ($contact): array => $this->contactRow($contact)),
        ]);
    }

    public function webhooks(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'webhooks', 'Webhooks', 'Configure callbacks assinados para eventos do Waflow.', [
            'webhooks' => $this->webhookRows($workspace->webhooks()->latest()->get()),
        ]);
    }

    public function apiKeys(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'api-keys', 'Chaves de API', 'Emita credenciais para integrações externas acessarem `/api/v1`.', [
            'apiKeys' => $this->apiKeyRows($workspace->apiKeys()->latest()->get()),
        ]);
    }

    public function developers(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'developers', 'APIs', 'Referência operacional dos endpoints versionados do Waflow.', [
            'messages_endpoint' => url('/api/v1/messages'),
        ]);
    }

    public function team(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'team', 'Equipe', 'Gerencie acesso administrativo ao workspace.', [
            'members' => $this->memberRows($workspace->members()->with('user')->latest()->get()),
        ]);
    }

    public function billing(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'billing', 'Plano e consumo', 'Acompanhe limites, uso mensal e status da assinatura.', [
            'metrics' => $this->metrics($workspace),
        ]);
    }

    public function settings(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'settings', 'Configurações', 'Defina informações de conta, workspace e operação.');
    }

    public function storeInstance(
        Request $request,
        WorkspaceContext $context,
        UsageLimiter $limiter,
        InstanceManager $instances,
    ): RedirectResponse {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $limiter->ensureInstances($workspace);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $instance = $workspace->instances()->create([
            'name' => $attributes['name'],
            'session_name' => Str::slug($workspace->slug.' '.$attributes['name']).'-'.Str::lower(Str::random(6)),
            'status' => 'provisioning',
        ]);
        $instances->provision($instance);

        return redirect()->route('panel.instances')->with('status', 'Instância criada.');
    }

    public function connectInstance(
        Request $request,
        WorkspaceContext $context,
        InstanceManager $instances,
        string $instance,
    ): RedirectResponse {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $model = $workspace->instances()->where('uuid', $instance)->firstOrFail();
        $instances->connect($model);

        return back()->with('status', 'Conexão iniciada. Leia o QR Code exibido.');
    }

    public function disconnectInstance(
        Request $request,
        WorkspaceContext $context,
        InstanceManager $instances,
        string $instance,
    ): RedirectResponse {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $model = $workspace->instances()->where('uuid', $instance)->firstOrFail();
        $instances->disconnect($model);

        return back()->with('status', 'Instância desconectada.');
    }

    public function storeFlow(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
        ]);

        $workspace->flows()->create([
            'name' => $attributes['name'],
            'trigger_type' => $attributes['trigger_type'],
            'status' => 'draft',
            'steps' => [
                ['type' => 'condition', 'label' => 'Nova condição'],
                ['type' => 'action', 'label' => 'Enviar mensagem'],
            ],
        ]);

        return redirect()->route('panel.flows')->with('status', 'Fluxo criado.');
    }

    public function storeContact(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:32'],
        ]);

        $workspace->contacts()->updateOrCreate(
            ['phone_number' => $attributes['phone_number']],
            ['name' => $attributes['name'] ?? null],
        );

        return redirect()->route('panel.contacts')->with('status', 'Contato salvo.');
    }

    public function storeWebhook(Request $request, WorkspaceContext $context, UsageLimiter $limiter): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $limiter->ensureWebhooks($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048', new PublicWebhookUrl],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', Rule::in(self::WEBHOOK_EVENTS)],
        ]);

        $secret = bin2hex(random_bytes(32));
        $workspace->webhooks()->create([
            'name' => $attributes['name'],
            'url' => $attributes['url'],
            'events' => $attributes['events'] ?? ['message.received'],
            'status' => 'active',
            'signing_secret' => $secret,
        ]);

        return redirect()
            ->route('panel.webhooks')
            ->with('status', 'Webhook criado.')
            ->with('created_webhook_secret', $secret);
    }

    public function destroyWebhook(Request $request, WorkspaceContext $context, Webhook $webhook): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        abort_unless($webhook->workspace_id === $workspace->id, 404);
        $webhook->delete();

        return back()->with('status', 'Webhook removido.');
    }

    public function storeApiKey(Request $request, WorkspaceContext $context, UsageLimiter $limiter): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $limiter->ensureApiKeys($workspace);

        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $issued = ApiKey::issue($workspace, $attributes['name']);

        return redirect()
            ->route('panel.api-keys')
            ->with('status', 'Chave de API criada.')
            ->with('created_api_token', $issued['plain_text_token']);
    }

    public function destroyApiKey(Request $request, WorkspaceContext $context, int $apiKey): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $workspace->apiKeys()->whereKey($apiKey)->firstOrFail()->delete();

        return back()->with('status', 'Chave de API removida.');
    }

    public function storeTeamMember(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $attributes = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['admin', 'support', 'developer', 'member'])],
        ]);

        $user = User::query()->where('email', $attributes['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Crie o usuário antes de adicioná-lo ao workspace.']);
        }

        $workspace->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $attributes['role'],
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        return redirect()->route('panel.team')->with('status', 'Membro adicionado.');
    }

    public function updateSettings(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'max:80'],
        ]);

        $workspace->update($attributes);

        return redirect()->route('panel.settings')->with('status', 'Configurações salvas.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(Request $request, Workspace $workspace, string $page, string $title, string $description, array $data = []): View
    {
        $workspace->loadMissing(['subscription.plan', 'members.user']);
        $plan = $workspace->subscription?->plan;

        return view('panel.app', [
            'title' => $title,
            'appPayload' => [
                'page' => $page,
                'title' => $title,
                'description' => $description,
                'csrfToken' => csrf_token(),
                'workspace' => $this->workspacePayload($workspace),
                'plan' => $this->planPayload($plan),
                'usage' => $this->usage($workspace),
                'navSections' => $this->navSections($request),
                'user' => $this->userPayload($request->user()),
                'routes' => $this->routesPayload(),
                'data' => $data,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function workspacePayload(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'timezone' => $workspace->timezone,
            'status' => $workspace->status,
            'subscription_status' => $workspace->subscription?->status,
            'current_period_ends_at_label' => $workspace->subscription?->current_period_ends_at?->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planPayload(?Plan $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'monthly_price_cents' => $plan->monthly_price_cents,
            'monthly_price_label' => 'R$ '.number_format($plan->monthly_price_cents / 100, 2, ',', '.'),
            'instance_limit' => $plan->instance_limit,
            'message_limit' => $plan->message_limit,
            'webhook_limit' => $plan->webhook_limit,
            'api_key_limit' => $plan->api_key_limit,
            'features' => $plan->features ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routesPayload(): array
    {
        return [
            'logout' => route('logout'),
            'pages' => [
                'dashboard' => route('dashboard'),
                'instances' => route('panel.instances'),
                'flows' => route('panel.flows'),
                'messages' => route('panel.messages'),
                'contacts' => route('panel.contacts'),
                'developers' => route('panel.developers'),
                'webhooks' => route('panel.webhooks'),
                'apiKeys' => route('panel.api-keys'),
                'team' => route('panel.team'),
                'billing' => route('panel.billing'),
                'settings' => route('panel.settings'),
            ],
            'instances' => ['store' => route('panel.instances.store')],
            'flows' => ['store' => route('panel.flows.store')],
            'contacts' => ['store' => route('panel.contacts.store')],
            'webhooks' => ['store' => route('panel.webhooks.store')],
            'apiKeys' => ['store' => route('panel.api-keys.store')],
            'team' => ['store' => route('panel.team.store')],
            'settings' => ['update' => route('panel.settings.update')],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function metrics(Workspace $workspace): array
    {
        $monthStart = now()->startOfMonth();

        return [
            'instances' => $workspace->instances()->count(),
            'connected' => $workspace->instances()->where('status', 'connected')->count(),
            'messages' => $workspace->messageEvents()->where('created_at', '>=', $monthStart)->count(),
            'failures' => $workspace->messageEvents()->where('status', 'failed')->where('created_at', '>=', $monthStart)->count(),
            'webhooks' => $workspace->webhooks()->count(),
            'api_keys' => $workspace->apiKeys()->count(),
            'contacts' => $workspace->contacts()->count(),
            'flows' => $workspace->flows()->count(),
        ];
    }

    /**
     * @return array<string, array{used: int, limit: int, percent: int}>
     */
    private function usage(Workspace $workspace): array
    {
        $limiter = app(UsageLimiter::class);
        $messageLimit = max($limiter->limit($workspace, 'messages'), 1);
        $instanceLimit = max($limiter->limit($workspace, 'instances'), 1);
        $apiKeyLimit = max($limiter->limit($workspace, 'api_keys'), 1);
        $webhookLimit = max($limiter->limit($workspace, 'webhooks'), 1);

        $messages = $workspace->messageEvents()->where('created_at', '>=', now()->startOfMonth())->count();
        $instances = $workspace->instances()->count();
        $apiKeys = $workspace->apiKeys()->count();
        $webhooks = $workspace->webhooks()->count();

        return [
            'messages' => ['used' => $messages, 'limit' => $messageLimit, 'percent' => $this->percent($messages, $messageLimit)],
            'instances' => ['used' => $instances, 'limit' => $instanceLimit, 'percent' => $this->percent($instances, $instanceLimit)],
            'api_keys' => ['used' => $apiKeys, 'limit' => $apiKeyLimit, 'percent' => $this->percent($apiKeys, $apiKeyLimit)],
            'webhooks' => ['used' => $webhooks, 'limit' => $webhookLimit, 'percent' => $this->percent($webhooks, $webhookLimit)],
        ];
    }

    private function percent(int $used, int $limit): int
    {
        return min(100, (int) round(($used / max($limit, 1)) * 100));
    }

    private function databaseOnline(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string, url: string, active: bool}>}>
     */
    private function navSections(Request $request): array
    {
        return collect([
            [
                'label' => 'Operação',
                'items' => [
                    ['label' => 'Visão geral', 'route' => 'dashboard', 'icon' => 'ki-element-11'],
                    ['label' => 'Instâncias', 'route' => 'panel.instances', 'icon' => 'ki-whatsapp'],
                    ['label' => 'Fluxos', 'route' => 'panel.flows', 'icon' => 'ki-route'],
                    ['label' => 'Mensagens', 'route' => 'panel.messages', 'icon' => 'ki-messages'],
                    ['label' => 'Contatos', 'route' => 'panel.contacts', 'icon' => 'ki-profile-circle'],
                ],
            ],
            [
                'label' => 'Desenvolvedores',
                'items' => [
                    ['label' => 'APIs', 'route' => 'panel.developers', 'icon' => 'ki-code'],
                    ['label' => 'Webhooks', 'route' => 'panel.webhooks', 'icon' => 'ki-abstract-26'],
                    ['label' => 'Chaves de API', 'route' => 'panel.api-keys', 'icon' => 'ki-key'],
                ],
            ],
            [
                'label' => 'Conta',
                'items' => [
                    ['label' => 'Equipe', 'route' => 'panel.team', 'icon' => 'ki-people'],
                    ['label' => 'Plano e consumo', 'route' => 'panel.billing', 'icon' => 'ki-credit-cart'],
                    ['label' => 'Configurações', 'route' => 'panel.settings', 'icon' => 'ki-setting-2'],
                ],
            ],
        ])->map(function (array $section) use ($request): array {
            $section['items'] = collect($section['items'])->map(fn (array $item): array => array_merge($item, [
                'url' => route($item['route']),
                'active' => $request->routeIs($item['route']),
            ]))->values()->all();

            return $section;
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instanceRows($instances): array
    {
        return $instances->map(fn ($instance): array => [
            'id' => $instance->id,
            'uuid' => $instance->uuid,
            'name' => $instance->name,
            'session_name' => $instance->session_name,
            'phone_number' => $instance->phone_number,
            'status' => $instance->status,
            'status_label' => str_replace('_', ' ', $instance->status),
            'qr_code' => data_get($instance->engine_payload, 'qr_code'),
            'connect_url' => route('panel.instances.connect', $instance->uuid),
            'disconnect_url' => route('panel.instances.disconnect', $instance->uuid),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flowRows($flows): array
    {
        return $flows->map(fn ($flow): array => [
            'id' => $flow->id,
            'uuid' => $flow->uuid,
            'name' => $flow->name,
            'trigger_type' => $flow->trigger_type,
            'trigger_label' => str_replace('_', ' ', $flow->trigger_type),
            'status' => $flow->status,
            'last_run_human' => $flow->last_run_at?->diffForHumans(),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function messageRows($messages): array
    {
        return $messages->map(fn ($message): array => $this->messageRow($message))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageRow($message): array
    {
        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'direction' => $message->direction,
            'from' => $message->from,
            'to' => $message->to,
            'status' => $message->status,
            'body' => data_get($message->payload, 'text')
                ?? data_get($message->payload, 'caption')
                ?? data_get($message->payload, 'body'),
            'instance_name' => $message->instance?->name,
            'contact_name' => $message->contact?->name,
            'created_human' => $message->created_at?->diffForHumans(),
            'created_at_label' => $message->created_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactRow($contact): array
    {
        return [
            'id' => $contact->id,
            'uuid' => $contact->uuid,
            'name' => $contact->name,
            'phone_number' => $contact->phone_number,
            'wa_id' => $contact->wa_id,
            'last_seen_human' => $contact->last_seen_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function webhookRows($webhooks): array
    {
        return $webhooks->map(fn ($webhook): array => [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'events' => $webhook->events ?? [],
            'events_label' => implode(', ', $webhook->events ?? []),
            'status' => $webhook->status,
            'delete_url' => route('panel.webhooks.destroy', $webhook),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function apiKeyRows($apiKeys): array
    {
        return $apiKeys->map(fn ($apiKey): array => [
            'id' => $apiKey->id,
            'name' => $apiKey->name,
            'token_prefix' => $apiKey->token_prefix,
            'abilities' => $apiKey->abilities ?? ['*'],
            'abilities_label' => implode(', ', $apiKey->abilities ?? ['*']),
            'last_used_human' => $apiKey->last_used_at?->diffForHumans(),
            'delete_url' => route('panel.api-keys.destroy', $apiKey->id),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function memberRows($members): array
    {
        return $members->map(fn ($member): array => [
            'id' => $member->id,
            'name' => $member->user->name,
            'email' => $member->user->email,
            'role' => $member->role,
            'status' => $member->status,
            'joined_at_label' => $member->joined_at?->format('d/m/Y'),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedRows($paginator, callable $mapper): array
    {
        $links = collect($paginator->linkCollection())->map(function (array $link): array {
            $label = strip_tags((string) $link['label']);
            $label = str_replace(['&laquo; Previous', 'Next &raquo;', 'Previous', 'Next'], ['Anterior', 'Próxima', 'Anterior', 'Próxima'], $label);

            return [
                'url' => $link['url'],
                'label' => $label,
                'active' => $link['active'],
            ];
        })->values()->all();

        return [
            'items' => $paginator->getCollection()->map($mapper)->values()->all(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'links' => $links,
        ];
    }

    private function assertWorkspaceCanOperate(Workspace $workspace): void
    {
        $subscription = $workspace->subscription;
        if ($subscription?->status === 'trialing' && $subscription->trial_ends_at?->isPast()) {
            $subscription->update(['status' => 'expired']);
        }

        abort_unless(
            $workspace->status === 'active' && in_array($subscription?->fresh()->status, ['active', 'trialing'], true),
            402,
            'A assinatura do workspace não está ativa.',
        );
    }
}
