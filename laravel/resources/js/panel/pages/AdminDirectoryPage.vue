<script setup>
import { computed } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelPagination from '../PanelPagination.vue';
import PanelServerTableToolbar from '../PanelServerTableToolbar.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, confirmSubmit, roleLabel, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { page, pageData, csrfToken, pages } = usePanelContext(props);
const filters = computed(() => pageData.value.filters || {});
const isCompanies = computed(() => page.value === 'admin-companies');
const isUsers = computed(() => page.value === 'admin-users');
const statuses = [
    { value: 'active', label: 'Ativo' },
    { value: 'suspended', label: 'Suspenso' },
];
const subscriptionStatuses = [
    { value: 'trialing', label: 'Período de teste' },
    { value: 'active', label: 'Ativo' },
    { value: 'past_due', label: 'Pagamento pendente' },
    { value: 'cancelled', label: 'Cancelado' },
    { value: 'expired', label: 'Expirado' },
];
</script>

<template>
    <PanelShell :payload="payload">
        <div v-if="isCompanies" class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Empresas da plataforma</h3><p class="kt-card-description">Cada empresa corresponde a um workspace isolado.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.companies?.total || 0 }} empresas</span></div>
            <PanelServerTableToolbar :action="pages.companies" :query="filters.search" :status="filters.status" :statuses="statuses" placeholder="Buscar empresa, slug ou proprietário" />
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                <thead><tr><th>Empresa</th><th>Proprietário</th><th>Plano</th><th>Estrutura</th><th>Status</th><th>Cadastro</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                <tbody>
                    <tr v-for="company in pageData.companies?.items" :key="company.uuid">
                        <td><div class="font-medium text-mono">{{ company.name }}</div><code class="text-xs">{{ company.slug }}</code></td><td><div>{{ company.owner }}</div><div class="text-xs text-muted-foreground">{{ company.owner_email }}</div></td><td><div>{{ company.plan || '-' }}</div><span class="text-xs text-muted-foreground">{{ statusLabel(company.subscription_status) }}</span></td><td>{{ company.members_count }} membros · {{ company.instances_count }} instâncias</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(company.status)}`">{{ statusLabel(company.status) }}</span></td><td>{{ company.created_at_label }}</td>
                        <td class="waflow-actions-column"><form class="flex justify-end" :action="company.status_url" method="post" @submit="company.status === 'active' ? confirmSubmit($event, `Suspender a empresa ${company.name}?`) : null"><input name="_token" type="hidden" :value="csrfToken"><input name="status" type="hidden" :value="company.status === 'active' ? 'suspended' : 'active'"><button class="kt-btn kt-btn-sm" :class="company.status === 'active' ? 'kt-btn-ghost waflow-action-destructive' : 'kt-btn-outline'" type="submit">{{ company.status === 'active' ? 'Suspender' : 'Reativar' }}</button></form></td>
                    </tr>
                    <tr v-if="!pageData.companies?.items?.length"><td colspan="7"><PanelEmptyState icon="ki-office-bag" title="Nenhuma empresa encontrada" description="Tente remover os filtros aplicados." /></td></tr>
                </tbody>
            </table></div></div>
            <div class="kt-card-footer"><PanelPagination v-if="pageData.companies?.total" :pagination="pageData.companies" label="empresas" /></div>
        </div>

        <div v-else-if="isUsers" class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Usuários cadastrados</h3><p class="kt-card-description">Contas que acessam empresas, parceiros ou a plataforma.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.users?.total || 0 }} usuários</span></div>
            <PanelServerTableToolbar :action="pages.users" :query="filters.search" placeholder="Buscar nome ou email" />
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                <thead><tr><th>Usuário</th><th>Workspaces</th><th>Papéis</th><th>Segurança</th><th>Cadastro</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                <tbody>
                    <tr v-for="account in pageData.users?.items" :key="account.id"><td><div class="font-medium text-mono">{{ account.name }}</div><div class="text-xs text-muted-foreground">{{ account.email }}</div><span v-if="account.is_platform_admin" class="kt-badge kt-badge-light kt-badge-primary mt-1">Admin global</span></td><td>{{ account.workspaces_count }}</td><td><span class="waflow-cell-preview" :title="account.roles_label">{{ account.roles_label || '-' }}</span></td><td><div>{{ account.email_verified ? 'Email verificado' : 'Email pendente' }}</div><div class="text-xs text-muted-foreground">2FA {{ account.two_factor_enabled ? 'ativo' : 'inativo' }}</div></td><td>{{ account.created_at_label }}</td><td class="waflow-actions-column"><form v-if="!account.is_current" class="flex justify-end" :action="account.admin_url" method="post" @submit="account.is_platform_admin ? confirmSubmit($event, `Remover o acesso global de ${account.name}?`) : null"><input name="_token" type="hidden" :value="csrfToken"><input name="is_platform_admin" type="hidden" :value="account.is_platform_admin ? 0 : 1"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ account.is_platform_admin ? 'Remover admin' : 'Tornar admin' }}</button></form><span v-else class="text-xs text-muted-foreground">Sua conta</span></td></tr>
                    <tr v-if="!pageData.users?.items?.length"><td colspan="6"><PanelEmptyState icon="ki-people" title="Nenhum usuário encontrado" /></td></tr>
                </tbody>
            </table></div></div>
            <div class="kt-card-footer"><PanelPagination v-if="pageData.users?.total" :pagination="pageData.users" label="usuários" /></div>
        </div>

        <div v-else class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Assinaturas</h3><p class="kt-card-description">Plano e situação comercial de cada empresa.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.subscriptions?.total || 0 }} assinaturas</span></div>
            <PanelServerTableToolbar :action="pages.subscriptions" :query="filters.search" :status="filters.status" :statuses="subscriptionStatuses" placeholder="Buscar empresa ou proprietário" />
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                <thead><tr><th>Empresa</th><th>Plano atual</th><th>Status</th><th>Ciclo</th><th>Fim do período</th><th class="text-end waflow-actions-column">Gerenciar</th></tr></thead>
                <tbody>
                    <tr v-for="subscription in pageData.subscriptions?.items" :key="subscription.id"><td><div class="font-medium text-mono">{{ subscription.workspace }}</div><div class="text-xs text-muted-foreground">{{ subscription.owner_email }}</div></td><td>{{ subscription.plan }} · {{ subscription.price_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(subscription.status)}`">{{ statusLabel(subscription.status) }}</span></td><td>{{ subscription.billing_cycle === 'monthly' ? 'Mensal' : subscription.billing_cycle }}</td><td>{{ subscription.period_end_label || '-' }}</td><td class="waflow-actions-column"><form class="flex flex-wrap justify-end gap-2" :action="subscription.update_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="patch"><select class="kt-input kt-input-sm w-36" name="plan_id" aria-label="Plano"><option v-for="plan in pageData.plans" :key="plan.id" :value="plan.id" :selected="plan.id === subscription.plan_id">{{ plan.name }}</option></select><select class="kt-input kt-input-sm w-36" name="status" aria-label="Status"><option v-for="option in subscriptionStatuses" :key="option.value" :value="option.value" :selected="option.value === subscription.status">{{ option.label }}</option></select><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Salvar</button></form></td></tr>
                    <tr v-if="!pageData.subscriptions?.items?.length"><td colspan="6"><PanelEmptyState icon="ki-credit-cart" title="Nenhuma assinatura encontrada" /></td></tr>
                </tbody>
            </table></div></div>
            <div class="kt-card-footer"><PanelPagination v-if="pageData.subscriptions?.total" :pagination="pageData.subscriptions" label="assinaturas" /></div>
        </div>
    </PanelShell>
</template>
