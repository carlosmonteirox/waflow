<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(Request $request): View
    {
        $monthStart = now()->startOfMonth();
        $activeSubscriptions = Subscription::query()->whereIn('status', ['active', 'trialing']);

        return $this->render($request, 'admin-dashboard', 'Administração', 'Controle global de empresas, parceiros, receita e operação da Waflow.', [
            'metrics' => [
                'workspaces' => Workspace::query()->count(),
                'active_workspaces' => Workspace::query()->where('status', 'active')->count(),
                'partners' => Partner::query()->where('status', 'active')->count(),
                'users' => User::query()->count(),
                'mrr_cents' => (clone $activeSubscriptions)->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->sum('plans.monthly_price_cents'),
                'messages_month' => DB::table('message_events')->where('created_at', '>=', $monthStart)->count(),
            ],
            'recentWorkspaces' => Workspace::query()->with(['owner', 'subscription.plan'])->latest()->limit(6)->get()->map(fn (Workspace $workspace): array => $this->workspaceRow($workspace))->all(),
            'recentPartners' => Partner::query()->with('owner')->withCount('workspaces')->latest()->limit(6)->get()->map(fn (Partner $partner): array => $this->partnerRow($partner))->all(),
        ]);
    }

    public function companies(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $query = Workspace::query()->with(['owner', 'subscription.plan'])->withCount(['members', 'instances']);
        $query->when($search !== '', function ($query) use ($search): void {
            $term = '%'.Str::lower($search).'%';
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                ->orWhereHas('owner', fn ($owners) => $owners->whereRaw('LOWER(email) LIKE ?', [$term])));
        });
        $query->when($status !== '', fn ($query) => $query->where('status', $status));

        return $this->render($request, 'admin-companies', 'Empresas', 'Gerencie todos os workspaces clientes da plataforma.', [
            'companies' => $this->pagination($query->latest()->paginate(15)->withQueryString(), fn (Workspace $workspace): array => $this->workspaceRow($workspace)),
            'filters' => compact('search', 'status'),
            'plans' => Plan::query()->orderBy('monthly_price_cents')->get()->map(fn (Plan $plan): array => $this->planRow($plan))->all(),
        ]);
    }

    public function users(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $query = User::query()->with('workspaces')->withCount('workspaces');
        $query->when($search !== '', function ($query) use ($search): void {
            $term = '%'.Str::lower($search).'%';
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(email) LIKE ?', [$term]));
        });

        return $this->render($request, 'admin-users', 'Usuários', 'Consulte acessos e controle administradores globais.', [
            'users' => $this->pagination($query->latest()->paginate(15)->withQueryString(), fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'two_factor_enabled' => $user->two_factor_enabled,
                'is_platform_admin' => $user->is_platform_admin,
                'workspaces_count' => $user->workspaces_count,
                'roles_label' => $user->workspaces->map(fn ($workspace) => $workspace->name.' · '.$workspace->pivot->role)->implode(', '),
                'created_at_label' => $user->created_at?->format('d/m/Y H:i'),
                'admin_url' => route('admin.users.admin', $user),
                'is_current' => $user->is($request->user()),
            ]),
            'filters' => compact('search'),
        ]);
    }

    public function partners(Request $request): View
    {
        $partners = Partner::query()->with('owner')->withCount(['workspaces', 'members'])->latest()->get();

        return $this->render($request, 'admin-partners', 'Parceiros', 'Cadastre revendedores e acompanhe suas carteiras de clientes.', [
            'partners' => $partners->map(fn (Partner $partner): array => $this->partnerRow($partner))->all(),
        ]);
    }

    public function plans(Request $request): View
    {
        return $this->render($request, 'admin-plans', 'Planos', 'Defina preços e limites comerciais disponíveis para os clientes.', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('monthly_price_cents')->get()->map(fn (Plan $plan): array => $this->planRow($plan))->all(),
        ]);
    }

    public function subscriptions(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $query = Subscription::query()->with(['workspace.owner', 'plan']);
        $query->when($search !== '', function ($query) use ($search): void {
            $term = '%'.Str::lower($search).'%';
            $query->whereHas('workspace', fn ($workspaces) => $workspaces->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereHas('owner', fn ($owners) => $owners->whereRaw('LOWER(email) LIKE ?', [$term])));
        });
        $query->when($status !== '', fn ($query) => $query->where('status', $status));

        return $this->render($request, 'admin-subscriptions', 'Assinaturas', 'Gerencie plano, ciclo e situação das assinaturas.', [
            'subscriptions' => $this->pagination($query->latest()->paginate(15)->withQueryString(), fn (Subscription $subscription): array => [
                'id' => $subscription->id,
                'workspace' => $subscription->workspace->name,
                'owner_email' => $subscription->workspace->owner?->email,
                'plan_id' => $subscription->plan_id,
                'plan' => $subscription->plan?->name,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'price_label' => $this->money($subscription->plan?->monthly_price_cents),
                'period_end_label' => $subscription->current_period_ends_at?->format('d/m/Y'),
                'update_url' => route('admin.subscriptions.update', $subscription),
            ]),
            'filters' => compact('search', 'status'),
            'plans' => Plan::query()->orderBy('monthly_price_cents')->get()->map(fn (Plan $plan): array => $this->planRow($plan))->all(),
        ]);
    }

    public function updateWorkspaceStatus(Request $request, Workspace $workspace): RedirectResponse
    {
        $workspace->update($request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]));

        return back()->with('status', 'Status da empresa atualizado.');
    }

    public function updateUserAdmin(Request $request, User $user): RedirectResponse
    {
        $attributes = $request->validate(['is_platform_admin' => ['required', 'boolean']]);
        abort_if($user->is($request->user()) && ! $attributes['is_platform_admin'], 409, 'Você não pode remover o próprio acesso administrativo.');
        $user->update($attributes);

        return back()->with('status', 'Acesso administrativo atualizado.');
    }

    public function storePartner(Request $request): RedirectResponse
    {
        $request->merge(['owner_email' => mb_strtolower(trim((string) $request->input('owner_email')))]);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'exists:users,email'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $owner = User::query()->where('email', mb_strtolower($attributes['owner_email']))->firstOrFail();
        $partner = Partner::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'name' => $attributes['name'],
            'slug' => $this->uniquePartnerSlug($attributes['name']),
            'brand_name' => $attributes['name'],
            'commission_percent' => $attributes['commission_percent'],
            'referral_code' => Partner::generateReferralCode(),
        ]);
        PartnerMember::query()->create([
            'partner_id' => $partner->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return back()->with('status', 'Parceiro criado e acesso do proprietário liberado.');
    }

    public function updatePartnerStatus(Request $request, Partner $partner): RedirectResponse
    {
        $partner->update($request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]));

        return back()->with('status', 'Status do parceiro atualizado.');
    }

    public function storePlan(Request $request): RedirectResponse
    {
        Plan::query()->create($this->validatePlan($request));

        return back()->with('status', 'Plano criado.');
    }

    public function updatePlan(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validatePlan($request, $plan));

        return back()->with('status', 'Plano atualizado.');
    }

    public function updateSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->update($request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'cancelled', 'expired'])],
        ]));

        return back()->with('status', 'Assinatura atualizada.');
    }

    private function render(Request $request, string $page, string $title, string $description, array $data): View
    {
        $hasPartner = $request->user()->partners()->wherePivot('status', 'active')->exists();
        $pages = [
            'dashboard' => route('admin.dashboard'),
            'companies' => route('admin.companies'),
            'users' => route('admin.users'),
            'partners' => route('admin.partners'),
            'plans' => route('admin.plans'),
            'subscriptions' => route('admin.subscriptions'),
        ];

        return view('admin.app', [
            'title' => $title,
            'component' => $page,
            'appPayload' => [
                'console' => 'admin',
                'page' => $page,
                'title' => $title,
                'description' => $description,
                'csrfToken' => csrf_token(),
                'user' => $request->user()->only(['id', 'name', 'email']),
                'workspace' => ['name' => 'Administração Waflow', 'slug' => 'admin', 'status' => 'active', 'member_role' => 'platform_admin', 'permissions' => []],
                'workspaces' => [],
                'plan' => ['name' => 'Plataforma'],
                'usage' => [],
                'routes' => [
                    'logout' => route('logout'),
                    'pages' => $pages,
                    'admin' => [
                        'partnersStore' => route('admin.partners.store'),
                        'plansStore' => route('admin.plans.store'),
                    ],
                ],
                'navSections' => $this->navigation($request),
                'contextLinks' => array_values(array_filter([
                    ['label' => 'Painel da empresa', 'icon' => 'ki-element-11', 'url' => route('dashboard')],
                    $hasPartner ? ['label' => 'Painel do parceiro', 'icon' => 'ki-shop', 'url' => route('partner.dashboard')] : null,
                ])),
                'data' => $data,
            ],
        ]);
    }

    private function navigation(Request $request): array
    {
        $sections = [
            ['label' => 'Plataforma', 'items' => [
                ['label' => 'Visão geral', 'route' => 'admin.dashboard', 'icon' => 'ki-element-11'],
                ['label' => 'Empresas', 'route' => 'admin.companies', 'icon' => 'ki-office-bag'],
                ['label' => 'Usuários', 'route' => 'admin.users', 'icon' => 'ki-people'],
                ['label' => 'Parceiros', 'route' => 'admin.partners', 'icon' => 'ki-shop'],
            ]],
            ['label' => 'Comercial', 'items' => [
                ['label' => 'Planos', 'route' => 'admin.plans', 'icon' => 'ki-price-tag'],
                ['label' => 'Assinaturas', 'route' => 'admin.subscriptions', 'icon' => 'ki-credit-cart'],
            ]],
        ];

        return collect($sections)->map(function (array $section) use ($request): array {
            $section['items'] = collect($section['items'])->map(fn (array $item): array => $item + [
                'url' => route($item['route']),
                'active' => $request->routeIs($item['route']),
            ])->all();

            return $section;
        })->all();
    }

    private function workspaceRow(Workspace $workspace): array
    {
        return [
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'status' => $workspace->status,
            'owner' => $workspace->owner?->name,
            'owner_email' => $workspace->owner?->email,
            'plan' => $workspace->subscription?->plan?->name,
            'subscription_status' => $workspace->subscription?->status,
            'members_count' => $workspace->members_count ?? $workspace->members()->count(),
            'instances_count' => $workspace->instances_count ?? $workspace->instances()->count(),
            'created_at_label' => $workspace->created_at?->format('d/m/Y H:i'),
            'status_url' => route('admin.companies.status', $workspace),
        ];
    }

    private function partnerRow(Partner $partner): array
    {
        $mrr = $this->partnerMrr($partner);

        return [
            'uuid' => $partner->uuid,
            'name' => $partner->name,
            'brand_name' => $partner->brand_name,
            'owner' => $partner->owner?->name,
            'owner_email' => $partner->owner?->email,
            'status' => $partner->status,
            'commission_percent' => (float) $partner->commission_percent,
            'referral_code' => $partner->referral_code,
            'customers_count' => $partner->workspaces_count ?? $partner->workspaces()->count(),
            'members_count' => $partner->members_count ?? $partner->members()->count(),
            'mrr_label' => $this->money($mrr),
            'commission_label' => $this->money((int) round($mrr * ((float) $partner->commission_percent / 100))),
            'status_url' => route('admin.partners.status', $partner),
        ];
    }

    private function planRow(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'monthly_price_cents' => $plan->monthly_price_cents,
            'monthly_price_label' => $this->money($plan->monthly_price_cents),
            'instance_limit' => $plan->instance_limit,
            'message_limit' => $plan->message_limit,
            'webhook_limit' => $plan->webhook_limit,
            'api_key_limit' => $plan->api_key_limit,
            'portal_limit' => $plan->portal_limit,
            'features' => $plan->features ?? [],
            'features_text' => implode("\n", $plan->features ?? []),
            'subscriptions_count' => $plan->subscriptions_count ?? $plan->subscriptions()->count(),
            'update_url' => route('admin.plans.update', $plan),
        ];
    }

    private function validatePlan(Request $request, ?Plan $plan = null): array
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('plans', 'slug')->ignore($plan)],
            'monthly_price_cents' => ['required', 'integer', 'min:0'],
            'instance_limit' => ['required', 'integer', 'min:1'],
            'message_limit' => ['required', 'integer', 'min:1'],
            'webhook_limit' => ['required', 'integer', 'min:0'],
            'api_key_limit' => ['required', 'integer', 'min:0'],
            'portal_limit' => ['required', 'integer', 'min:0'],
            'features_text' => ['nullable', 'string', 'max:5000'],
        ]);
        $attributes['features'] = collect(preg_split('/\r\n|\r|\n/', $attributes['features_text'] ?? ''))->map(fn ($feature) => trim($feature))->filter()->values()->all();
        unset($attributes['features_text']);

        return $attributes;
    }

    private function partnerMrr(Partner $partner): int
    {
        return (int) $partner->workspaces()->wherePivot('status', 'active')
            ->join('subscriptions', 'subscriptions.workspace_id', '=', 'workspaces.id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->sum('plans.monthly_price_cents');
    }

    private function pagination(LengthAwarePaginator $paginator, callable $transform): array
    {
        return [
            'items' => $paginator->getCollection()->map($transform)->values()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'prev_page_url' => $paginator->previousPageUrl(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }

    private function money(?int $cents): string
    {
        return 'R$ '.number_format(($cents ?? 0) / 100, 2, ',', '.');
    }

    private function uniquePartnerSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'parceiro';
        $slug = $base;
        $counter = 2;
        while (Partner::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
