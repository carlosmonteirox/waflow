<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ApiKey;
use App\Models\Plan;
use App\Models\Portal;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Rules\PublicWebhookUrl;
use App\Services\AutomationDefinition;
use App\Services\AutomationExecutor;
use App\Services\BillingService;
use App\Services\InstanceManager;
use App\Services\OpenWaClient;
use App\Services\TrialAbuseService;
use App\Services\UsageLimiter;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PanelController extends Controller
{
    private const DEVELOPER_API_ABILITIES = [
        'workspace:read', 'instances:read', 'instances:write', 'messages:read', 'messages:write',
        'contacts:read', 'contacts:write', 'flows:read', 'flows:write', 'groups:read', 'channels:read',
        'webhooks:read', 'webhooks:write', 'portals:read', 'portals:write', 'billing:read', 'api-keys:read',
    ];

    private const WEBHOOK_EVENTS = [
        '*', 'message.received', 'message.sent', 'message.ack', 'message.failed',
        'message.revoked', 'message.reaction', 'message.edited', 'session.status',
        'session.qr', 'session.authenticated', 'session.disconnected', 'automation.action',
        'automation.completed',
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
            'instances' => $this->instanceRows($workspace->instances()->latest()->get()),
            'executions' => $workspace->flowExecutions()->with('flow')->latest()->limit(20)->get()->map(fn ($execution) => [
                'uuid' => $execution->uuid,
                'flow_name' => $execution->flow?->name,
                'trigger_type' => $execution->trigger_type,
                'status' => $execution->status,
                'error' => $execution->error,
                'created_at_label' => $execution->created_at?->format('d/m/Y H:i'),
            ])->values()->all(),
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

    public function portals(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'portals', 'Portais', 'Gerencie os sites autorizados a operar com este workspace.', [
            'portals' => $workspace->portals()->latest()->get()->map(fn ($portal): array => [
                'uuid' => $portal->uuid,
                'name' => $portal->name,
                'url' => $portal->url,
                'domain' => $portal->domain,
                'status' => $portal->status,
                'verified_at_label' => $portal->verified_at?->format('d/m/Y H:i'),
                'verify_url' => route('panel.portals.verify', $portal),
                'delete_url' => route('panel.portals.destroy', $portal),
            ])->values()->all(),
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
            'openapi_url' => route('openapi'),
        ]);
    }

    public function team(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'team', 'Equipe', 'Gerencie acesso administrativo ao workspace.', [
            'members' => $this->memberRows($workspace->members()->with('user')->latest()->get()),
            'invitations' => $workspace->invitations()->latest()->get()->map(fn ($invitation) => [
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at_label' => $invitation->expires_at?->format('d/m/Y H:i'),
                'revoke_url' => route('panel.team.invitations.destroy', $invitation),
            ])->values()->all(),
        ]);
    }

    public function billing(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'billing', 'Plano e consumo', 'Acompanhe limites, uso mensal e status da assinatura.', [
            'metrics' => $this->metrics($workspace),
            'plans' => Plan::query()->orderBy('monthly_price_cents')->get()->map(fn ($plan) => $this->planPayload($plan))->all(),
            'payment_methods' => config('billing.available_payment_methods'),
            'provider' => config('billing.driver'),
            'provider_status' => config('billing.driver') === 'pending' ? 'pending_selection' : 'configured',
            'checkouts' => $workspace->checkoutSessions()->with('plan')->latest()->limit(10)->get()->map(fn ($checkout) => [
                'uuid' => $checkout->uuid,
                'plan' => $checkout->plan?->name,
                'payment_method' => $checkout->payment_method,
                'status' => $checkout->status,
                'amount_label' => 'R$ '.number_format($checkout->amount_cents / 100, 2, ',', '.'),
                'checkout_url' => $checkout->checkout_url,
                'pix_copy_paste' => data_get($checkout->provider_payload, 'pix_copy_paste'),
                'provider_status' => data_get($checkout->provider_payload, 'status'),
                'created_at_label' => $checkout->created_at?->format('d/m/Y H:i'),
            ])->all(),
            'invoices' => $workspace->invoices()->latest()->limit(10)->get()->map(fn ($invoice) => [
                'uuid' => $invoice->uuid,
                'number' => $invoice->number,
                'status' => $invoice->status,
                'amount_label' => 'R$ '.number_format($invoice->amount_cents / 100, 2, ',', '.'),
                'due_at_label' => $invoice->due_at?->format('d/m/Y'),
            ])->all(),
        ]);
    }

    public function settings(Request $request, WorkspaceContext $context): View
    {
        $workspace = $context->forUser($request->user());

        return $this->render($request, $workspace, 'settings', 'Configurações', 'Defina informações de conta, workspace e operação.');
    }

    public function switchWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->members()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
        $request->session()->put('waflow.workspace_uuid', $workspace->uuid);

        return redirect()->route('dashboard')->with('status', 'Workspace alterado para '.$workspace->name.'.');
    }

    public function storeInstance(
        Request $request,
        WorkspaceContext $context,
        UsageLimiter $limiter,
        InstanceManager $instances,
    ): RedirectResponse {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $instance = $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes) {
            $limiter->ensureInstances($workspace);

            return $workspace->instances()->create([
                'name' => $attributes['name'],
                'session_name' => Str::slug($workspace->slug.' '.$attributes['name']).'-'.Str::lower(Str::random(6)),
                'status' => 'provisioning',
            ]);
        });
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

    public function storeFlow(Request $request, WorkspaceContext $context, AutomationDefinition $definition): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', Rule::in(['message_received', 'message_failed', 'instance_connected'])],
            'status' => ['nullable', Rule::in(['draft', 'active'])],
            'condition_field' => ['nullable', 'string', 'max:120'],
            'condition_operator' => ['nullable', Rule::in(['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'exists', 'in'])],
            'condition_value' => ['nullable', 'string', 'max:500'],
            'instance_uuid' => ['nullable', 'uuid'],
            'recipient' => ['nullable', 'string', 'max:128'],
            'message_text' => ['nullable', 'string', 'max:4096'],
        ]);

        $steps = [];
        if (filled($attributes['condition_field'] ?? null)) {
            $steps[] = [
                'type' => 'condition',
                'field' => $attributes['condition_field'],
                'operator' => $attributes['condition_operator'] ?? 'contains',
                'value' => $attributes['condition_value'] ?? null,
            ];
        }
        if (filled($attributes['message_text'] ?? null)) {
            $steps[] = [
                'type' => 'action',
                'action' => 'send_message',
                'instance_uuid' => $attributes['instance_uuid'] ?? '{{trigger.instance_uuid}}',
                'to' => $attributes['recipient'] ?? '{{trigger.from}}',
                'message_type' => 'text',
                'text' => $attributes['message_text'],
            ];
        }

        $workspace->flows()->create([
            'name' => $attributes['name'],
            'trigger_type' => $attributes['trigger_type'],
            'status' => $attributes['status'] ?? 'draft',
            'steps' => $definition->validate($steps),
        ]);

        return redirect()->route('panel.flows')->with('status', 'Fluxo criado.');
    }

    public function toggleFlow(Request $request, WorkspaceContext $context, string $flow): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $model = $workspace->flows()->where('uuid', $flow)->firstOrFail();
        $model->update(['status' => $model->status === 'active' ? 'paused' : 'active']);

        return back()->with('status', 'Status do fluxo atualizado.');
    }

    public function testFlow(Request $request, WorkspaceContext $context, AutomationExecutor $executor, string $flow): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $model = $workspace->flows()->where('uuid', $flow)->firstOrFail();
        $originalStatus = $model->status;
        $model->update(['status' => 'active']);
        try {
            $executor->dispatch($workspace, $model->trigger_type, [
                'instance_uuid' => $workspace->instances()->where('status', 'connected')->value('uuid'),
                'from' => '5500000000000@c.us',
                'body' => 'Teste manual do painel Waflow',
            ], 'panel:'.(string) Str::uuid());
        } finally {
            $model->update(['status' => $originalStatus]);
        }

        return back()->with('status', 'Execução de teste adicionada à fila.');
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

    public function sendMessage(Request $request, WorkspaceContext $context, UsageLimiter $limiter): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'instance_uuid' => ['required', 'uuid'],
            'to' => ['required', 'string', 'max:128'],
            'text' => ['required', 'string', 'max:4096'],
        ]);

        $message = $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes) {
            $limiter->ensureMessages($workspace);
            $instance = $workspace->instances()->where('uuid', $attributes['instance_uuid'])->firstOrFail();
            abort_unless($instance->status === 'connected', 409, 'A instância do WhatsApp não está conectada.');

            return $workspace->messageEvents()->create([
                'whatsapp_instance_id' => $instance->id,
                'direction' => 'outbound',
                'status' => 'queued',
                'to' => $attributes['to'],
                'idempotency_key' => 'panel:'.(string) Str::uuid(),
                'payload' => ['type' => 'text', 'text' => $attributes['text']],
            ]);
        });
        SendWhatsAppMessage::dispatch($message->id);

        return redirect()->route('panel.messages')->with('status', 'Mensagem adicionada à fila.');
    }

    public function storePortal(Request $request, WorkspaceContext $context, UsageLimiter $limiter, TrialAbuseService $abuse): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);
        $domain = $abuse->domainFromUrl($attributes['url']);
        $plainToken = Str::random(48);

        $limiter->serialized($workspace, function () use ($workspace, $limiter, $abuse, $attributes, $domain, $plainToken): void {
            $limiter->ensurePortals($workspace);
            $abuse->claimTrialIdentifier($workspace, 'portal_domain', $domain);
            $workspace->portals()->create([
                'name' => $attributes['name'],
                'url' => rtrim($attributes['url'], '/'),
                'domain' => $domain,
                'status' => 'pending',
                'verification_token' => hash('sha256', $plainToken),
            ]);
        });

        return redirect()->route('panel.portals')
            ->with('status', 'Portal adicionado. Use o token abaixo para verificá-lo.')
            ->with('portal_verification_token', $plainToken);
    }

    public function verifyPortal(Request $request, WorkspaceContext $context, Portal $portal): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        abort_unless($portal->workspace_id === $workspace->id, 404);
        $attributes = $request->validate(['verification_token' => ['required', 'string', 'max:255']]);
        abort_unless(hash_equals($portal->verification_token, hash('sha256', $attributes['verification_token'])), 422, 'Token de verificação inválido.');
        $portal->update(['status' => 'verified', 'verified_at' => now()]);

        return back()->with('status', 'Portal verificado.');
    }

    public function destroyPortal(Request $request, WorkspaceContext $context, Portal $portal): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        abort_unless($portal->workspace_id === $workspace->id, 404);
        $portal->delete();

        return back()->with('status', 'Portal removido.');
    }

    public function storeWebhook(Request $request, WorkspaceContext $context, UsageLimiter $limiter): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048', new PublicWebhookUrl],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', Rule::in(self::WEBHOOK_EVENTS)],
        ]);

        $secret = bin2hex(random_bytes(32));
        $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes, $secret): void {
            $limiter->ensureWebhooks($workspace);
            $workspace->webhooks()->create([
                'name' => $attributes['name'],
                'url' => $attributes['url'],
                'events' => $attributes['events'] ?? ['message.received'],
                'status' => 'active',
                'signing_secret' => $secret,
            ]);
        });

        return redirect()
            ->route('panel.webhooks')
            ->with('status', 'Webhook criado.')
            ->with('created_webhook_secret', $secret);
    }

    public function destroyWebhook(Request $request, WorkspaceContext $context, Webhook $webhook): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        abort_unless($webhook->workspace_id === $workspace->id, 404);
        $webhook->delete();

        return back()->with('status', 'Webhook removido.');
    }

    public function storeApiKey(Request $request, WorkspaceContext $context, UsageLimiter $limiter): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        $this->assertWorkspaceCanOperate($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $role = $workspace->members()->where('user_id', $request->user()->id)->value('role');
        $abilities = in_array($role, ['owner', 'admin'], true) ? ['*'] : self::DEVELOPER_API_ABILITIES;
        $issued = $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes, $abilities): array {
            $limiter->ensureApiKeys($workspace);

            return ApiKey::issue($workspace, $attributes['name'], $abilities);
        });

        return redirect()
            ->route('panel.api-keys')
            ->with('status', 'Chave de API criada.')
            ->with('created_api_token', $issued['plain_text_token']);
    }

    public function destroyApiKey(Request $request, WorkspaceContext $context, int $apiKey): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin', 'developer']);
        $workspace->apiKeys()->whereKey($apiKey)->firstOrFail()->delete();

        return back()->with('status', 'Chave de API removida.');
    }

    public function storeTeamMember(Request $request, WorkspaceContext $context, WorkspaceInvitationService $invitations): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
        $attributes = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(['admin', 'support', 'developer', 'member'])],
        ]);

        $invitations->invite($workspace, $attributes['email'], $attributes['role'], $request->user());

        return redirect()->route('panel.team')->with('status', 'Convite enviado por e-mail.');
    }

    public function revokeTeamInvitation(Request $request, WorkspaceContext $context, WorkspaceInvitation $invitation, WorkspaceInvitationService $invitations): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
        abort_unless($invitation->workspace_id === $workspace->id, 404);
        $invitations->revoke($invitation);

        return back()->with('status', 'Convite revogado.');
    }

    public function createCheckout(Request $request, WorkspaceContext $context, BillingService $billing): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
        $attributes = $request->validate([
            'plan_slug' => ['required', Rule::exists('plans', 'slug')],
            'payment_method' => ['required', Rule::in(config('billing.available_payment_methods'))],
            'payer_name' => [Rule::requiredIf(config('billing.driver') === 'inter'), 'nullable', 'string', 'max:120'],
            'payer_tax_id' => [Rule::requiredIf(config('billing.driver') === 'inter'), 'nullable', 'string', 'max:18'],
        ]);
        $plan = Plan::query()->where('slug', $attributes['plan_slug'])->firstOrFail();
        $checkout = $billing->createCheckout($workspace, $request->user(), $plan, $attributes['payment_method'], [
            'name' => $attributes['payer_name'] ?? null,
            'tax_id' => $attributes['payer_tax_id'] ?? null,
        ]);

        return back()->with('status', $checkout->status === 'provider_pending'
            ? 'Checkout preparado. Aguardando a definição do provedor de pagamentos.'
            : ($checkout->provider === 'inter' ? 'Pix Automático criado. Autorize a recorrência pelo código Pix.' : 'Checkout criado.'));
    }

    public function cancelSubscription(Request $request, WorkspaceContext $context, BillingService $billing): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
        $billing->cancel($workspace->subscription()->firstOrFail(), true);

        return back()->with('status', 'A assinatura será cancelada ao final do ciclo.');
    }

    public function resumeSubscription(Request $request, WorkspaceContext $context, BillingService $billing): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
        $billing->resume($workspace->subscription()->firstOrFail());

        return back()->with('status', 'Renovação da assinatura reativada.');
    }

    public function updateSettings(Request $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->forUser($request->user());
        $this->assertWorkspaceRole($request, $workspace, ['owner', 'admin']);
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

        return view('panel.'.$page, [
            'title' => $title,
            'appPayload' => [
                'page' => $page,
                'title' => $title,
                'description' => $description,
                'csrfToken' => csrf_token(),
                'workspace' => $this->workspacePayload($workspace, $request->user()),
                'workspaces' => $request->user()->workspaces()->wherePivot('status', 'active')->orderBy('name')->get()->map(fn ($available): array => [
                    'uuid' => $available->uuid,
                    'name' => $available->name,
                    'role' => $available->pivot->role,
                    'active' => $available->id === $workspace->id,
                    'select_url' => route('panel.workspaces.select', $available),
                ])->values()->all(),
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
    private function workspacePayload(Workspace $workspace, User $user): array
    {
        $role = $workspace->members()->where('user_id', $user->id)->value('role');

        return [
            'id' => $workspace->id,
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'timezone' => $workspace->timezone,
            'status' => $workspace->status,
            'subscription_status' => $workspace->subscription?->status,
            'current_period_ends_at_label' => $workspace->subscription?->current_period_ends_at?->format('d/m/Y'),
            'cancel_at_period_end' => (bool) $workspace->subscription?->cancel_at_period_end,
            'payment_provider' => $workspace->subscription?->payment_provider,
            'member_role' => $role,
            'permissions' => [
                'manage_team' => in_array($role, ['owner', 'admin'], true),
                'manage_billing' => in_array($role, ['owner', 'admin'], true),
                'manage_settings' => in_array($role, ['owner', 'admin'], true),
                'manage_integrations' => in_array($role, ['owner', 'admin', 'developer'], true),
            ],
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
            'portal_limit' => $plan->portal_limit,
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
                'portals' => route('panel.portals'),
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
            'messages' => ['store' => route('panel.messages.store')],
            'portals' => ['store' => route('panel.portals.store')],
            'webhooks' => ['store' => route('panel.webhooks.store')],
            'apiKeys' => ['store' => route('panel.api-keys.store')],
            'team' => ['store' => route('panel.team.store')],
            'billing' => [
                'checkout' => route('panel.billing.checkout'),
                'cancel' => route('panel.billing.cancel'),
                'resume' => route('panel.billing.resume'),
            ],
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
        $portalLimit = max($limiter->limit($workspace, 'portals'), 1);

        $messages = $workspace->messageEvents()->where('direction', 'outbound')->where('created_at', '>=', now()->startOfMonth())->count();
        $instances = $workspace->instances()->count();
        $apiKeys = $workspace->apiKeys()->count();
        $webhooks = $workspace->webhooks()->count();
        $portals = $workspace->portals()->count();

        return [
            'messages' => ['used' => $messages, 'limit' => $messageLimit, 'percent' => $this->percent($messages, $messageLimit)],
            'instances' => ['used' => $instances, 'limit' => $instanceLimit, 'percent' => $this->percent($instances, $instanceLimit)],
            'api_keys' => ['used' => $apiKeys, 'limit' => $apiKeyLimit, 'percent' => $this->percent($apiKeys, $apiKeyLimit)],
            'webhooks' => ['used' => $webhooks, 'limit' => $webhookLimit, 'percent' => $this->percent($webhooks, $webhookLimit)],
            'portals' => ['used' => $portals, 'limit' => $portalLimit, 'percent' => $this->percent($portals, $portalLimit)],
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
                    ['label' => 'Portais', 'route' => 'panel.portals', 'icon' => 'ki-global'],
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
            'run_count' => $flow->run_count,
            'failure_count' => $flow->failure_count,
            'last_error' => $flow->last_error,
            'toggle_url' => route('panel.flows.toggle', $flow->uuid),
            'test_url' => route('panel.flows.test', $flow->uuid),
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

    private function assertWorkspaceRole(Request $request, Workspace $workspace, array $roles): void
    {
        $role = $workspace->members()->where('user_id', $request->user()->id)->where('status', 'active')->value('role');
        abort_unless(in_array($role, $roles, true), 403, 'Seu perfil não permite esta operação.');
    }
}
