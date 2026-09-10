<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PartnerContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function dashboard(Request $request, PartnerContext $context): View
    {
        $partner = $context->forUser($request->user());
        $workspaces = $partner->workspaces()->with(['owner', 'subscription.plan'])->latest('partner_workspaces.created_at')->get();
        $mrr = $this->mrr($partner);

        return $this->render($request, $partner, 'partner-dashboard', 'Visão geral do parceiro', 'Acompanhe sua carteira, receita indicada e crescimento comercial.', [
            'metrics' => [
                'customers' => $workspaces->count(),
                'active_customers' => $workspaces->where('status', 'active')->count(),
                'mrr_cents' => $mrr,
                'commission_cents' => (int) round($mrr * ((float) $partner->commission_percent / 100)),
            ],
            'referral_url' => route('register', ['partner' => $partner->referral_code]),
            'recentCustomers' => $workspaces->take(6)->map(fn (Workspace $workspace): array => $this->customerRow($partner, $workspace))->all(),
        ]);
    }

    public function customers(Request $request, PartnerContext $context): View
    {
        $partner = $context->forUser($request->user());
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $query = $partner->workspaces()->with(['owner', 'subscription.plan'])->withCount(['members', 'instances']);
        $query->when($search !== '', function ($query) use ($search): void {
            $term = '%'.Str::lower($search).'%';
            $query->where(fn ($builder) => $builder->whereRaw('LOWER(workspaces.name) LIKE ?', [$term])
                ->orWhereHas('owner', fn ($owners) => $owners->whereRaw('LOWER(email) LIKE ?', [$term])));
        });
        $query->when($status !== '', fn ($query) => $query->where('workspaces.status', $status));

        return $this->render($request, $partner, 'partner-customers', 'Clientes', 'Empresas cadastradas pelo seu link comercial.', [
            'customers' => $this->pagination($query->latest('partner_workspaces.created_at')->paginate(15)->withQueryString(), fn (Workspace $workspace): array => $this->customerRow($partner, $workspace)),
            'filters' => compact('search', 'status'),
            'referral_url' => route('register', ['partner' => $partner->referral_code]),
        ]);
    }

    public function finance(Request $request, PartnerContext $context): View
    {
        $partner = $context->forUser($request->user());
        $customers = $partner->workspaces()->with(['owner', 'subscription.plan'])->get();
        $mrr = $this->mrr($partner);

        return $this->render($request, $partner, 'partner-finance', 'Receita e comissões', 'Visualize a base de cálculo mensal da sua operação.', [
            'summary' => [
                'mrr_label' => $this->money($mrr),
                'commission_percent' => (float) $partner->commission_percent,
                'commission_label' => $this->money((int) round($mrr * ((float) $partner->commission_percent / 100))),
                'active_subscriptions' => $customers->filter(fn ($workspace) => in_array($workspace->subscription?->status, ['active', 'trialing'], true))->count(),
            ],
            'customers' => $customers->map(fn (Workspace $workspace): array => $this->customerRow($partner, $workspace))->all(),
        ]);
    }

    public function settings(Request $request, PartnerContext $context): View
    {
        $partner = $context->forUser($request->user());
        $role = $this->role($request, $partner);

        return $this->render($request, $partner, 'partner-settings', 'Configurações do parceiro', 'Gerencie marca, domínio, equipe e informações comerciais.', [
            'partner' => [
                'name' => $partner->name,
                'brand_name' => $partner->brand_name,
                'primary_color' => $partner->primary_color,
                'custom_domain' => $partner->custom_domain,
                'referral_code' => $partner->referral_code,
                'referral_url' => route('register', ['partner' => $partner->referral_code]),
                'commission_percent' => (float) $partner->commission_percent,
            ],
            'members' => $partner->members()->with('user')->get()->map(fn (PartnerMember $member): array => [
                'id' => $member->id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role,
                'status' => $member->status,
                'joined_at_label' => $member->joined_at?->format('d/m/Y'),
                'remove_url' => route('partner.members.destroy', $member),
            ])->all(),
            'can_manage' => in_array($role, ['owner', 'admin'], true),
        ]);
    }

    public function switchPartner(Request $request, Partner $partner): RedirectResponse
    {
        abort_unless($partner->members()->where('user_id', $request->user()->id)->where('status', 'active')->exists(), 404);
        $request->session()->put('waflow.partner_uuid', $partner->uuid);

        return redirect()->route('partner.dashboard')->with('status', 'Parceiro alterado para '.$partner->name.'.');
    }

    public function updateSettings(Request $request, PartnerContext $context): RedirectResponse
    {
        $partner = $context->forUser($request->user());
        $this->assertCanManage($request, $partner);
        $attributes = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?!https?:\/\/)[a-z0-9.-]+$/i', Rule::unique('partners', 'custom_domain')->ignore($partner)],
        ]);
        $attributes['custom_domain'] = filled($attributes['custom_domain'] ?? null) ? Str::lower($attributes['custom_domain']) : null;
        $partner->update($attributes);

        return back()->with('status', 'Identidade do parceiro atualizada.');
    }

    public function storeMember(Request $request, PartnerContext $context): RedirectResponse
    {
        $partner = $context->forUser($request->user());
        $this->assertCanManage($request, $partner);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $attributes = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in(['admin', 'sales', 'support'])],
        ]);
        $user = User::query()->where('email', mb_strtolower($attributes['email']))->firstOrFail();
        $partner->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $attributes['role'], 'status' => 'active', 'joined_at' => now()],
        );

        return back()->with('status', 'Membro adicionado à equipe do parceiro.');
    }

    public function destroyMember(Request $request, PartnerMember $member, PartnerContext $context): RedirectResponse
    {
        $partner = $context->forUser($request->user());
        $this->assertCanManage($request, $partner);
        abort_unless($member->partner_id === $partner->id, 404);
        abort_if($member->role === 'owner', 409, 'O proprietário do parceiro não pode ser removido.');
        $member->delete();

        return back()->with('status', 'Membro removido da equipe do parceiro.');
    }

    private function render(Request $request, Partner $partner, string $page, string $title, string $description, array $data): View
    {
        $role = $this->role($request, $partner);
        $pages = [
            'dashboard' => route('partner.dashboard'),
            'customers' => route('partner.customers'),
            'finance' => route('partner.finance'),
            'settings' => route('partner.settings'),
        ];

        return view('partner.app', [
            'title' => $title,
            'component' => $page,
            'appPayload' => [
                'console' => 'partner',
                'page' => $page,
                'title' => $title,
                'description' => $description,
                'csrfToken' => csrf_token(),
                'user' => $request->user()->only(['id', 'name', 'email']),
                'workspace' => ['name' => $partner->brand_name ?: $partner->name, 'slug' => $partner->slug, 'status' => $partner->status, 'member_role' => 'partner_'.$role, 'permissions' => []],
                'workspaces' => $request->user()->partners()->wherePivot('status', 'active')->get()->map(fn (Partner $available): array => [
                    'uuid' => $available->uuid,
                    'name' => $available->brand_name ?: $available->name,
                    'role' => 'partner_'.$available->pivot->role,
                    'active' => $available->id === $partner->id,
                    'select_url' => route('partner.select', $available),
                ])->all(),
                'plan' => ['name' => 'Parceiro · '.number_format((float) $partner->commission_percent, 2, ',', '.').'%'],
                'usage' => [],
                'routes' => [
                    'logout' => route('logout'),
                    'pages' => $pages,
                    'partner' => [
                        'settingsUpdate' => route('partner.settings.update'),
                        'membersStore' => route('partner.members.store'),
                    ],
                ],
                'navSections' => $this->navigation($request),
                'contextLinks' => array_values(array_filter([
                    ['label' => 'Painel da empresa', 'icon' => 'ki-element-11', 'url' => route('dashboard')],
                    $request->user()->is_platform_admin ? ['label' => 'Administração Waflow', 'icon' => 'ki-security-user', 'url' => route('admin.dashboard')] : null,
                ])),
                'data' => $data,
            ],
        ]);
    }

    private function navigation(Request $request): array
    {
        $sections = [
            ['label' => 'Parceiro', 'items' => [
                ['label' => 'Visão geral', 'route' => 'partner.dashboard', 'icon' => 'ki-element-11'],
                ['label' => 'Clientes', 'route' => 'partner.customers', 'icon' => 'ki-office-bag'],
                ['label' => 'Receita e comissões', 'route' => 'partner.finance', 'icon' => 'ki-chart-line-up'],
            ]],
            ['label' => 'Conta', 'items' => [
                ['label' => 'Marca e equipe', 'route' => 'partner.settings', 'icon' => 'ki-setting-2'],
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

    private function customerRow(Partner $partner, Workspace $workspace): array
    {
        $price = $workspace->subscription?->plan?->monthly_price_cents ?? 0;
        $commission = $workspace->pivot?->commission_percent ?? $partner->commission_percent;

        return [
            'uuid' => $workspace->uuid,
            'name' => $workspace->name,
            'owner' => $workspace->owner?->name,
            'owner_email' => $workspace->owner?->email,
            'status' => $workspace->status,
            'subscription_status' => $workspace->subscription?->status,
            'plan' => $workspace->subscription?->plan?->name,
            'monthly_price_label' => $this->money($price),
            'commission_percent' => (float) $commission,
            'commission_label' => $this->money((int) round($price * ((float) $commission / 100))),
            'members_count' => $workspace->members_count ?? $workspace->members()->count(),
            'instances_count' => $workspace->instances_count ?? $workspace->instances()->count(),
            'referred_at_label' => filled($workspace->pivot?->referred_at)
                ? Carbon::parse($workspace->pivot->referred_at)->format('d/m/Y')
                : (filled($workspace->pivot?->created_at) ? Carbon::parse($workspace->pivot->created_at)->format('d/m/Y') : null),
        ];
    }

    private function mrr(Partner $partner): int
    {
        return (int) $partner->workspaces()->wherePivot('status', 'active')
            ->join('subscriptions', 'subscriptions.workspace_id', '=', 'workspaces.id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->sum('plans.monthly_price_cents');
    }

    private function role(Request $request, Partner $partner): string
    {
        return (string) $partner->members()->where('user_id', $request->user()->id)->where('status', 'active')->value('role');
    }

    private function assertCanManage(Request $request, Partner $partner): void
    {
        abort_unless(in_array($this->role($request, $partner), ['owner', 'admin'], true), 403, 'Seu perfil não permite gerenciar o parceiro.');
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

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
