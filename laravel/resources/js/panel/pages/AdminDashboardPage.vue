<script setup>
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, formatNumber, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, pages } = usePanelContext(props);

function money(cents) {
    return Number(cents || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const cards = [
    { key: 'workspaces', label: 'Empresas', icon: 'ki-office-bag', href: 'companies', format: formatNumber },
    { key: 'active_workspaces', label: 'Empresas ativas', icon: 'ki-check-circle', href: 'companies', format: formatNumber },
    { key: 'partners', label: 'Parceiros ativos', icon: 'ki-shop', href: 'partners', format: formatNumber },
    { key: 'users', label: 'Usuários', icon: 'ki-people', href: 'users', format: formatNumber },
    { key: 'mrr_cents', label: 'MRR contratado', icon: 'ki-chart-line-up', href: 'subscriptions', format: money },
    { key: 'messages_month', label: 'Mensagens no mês', icon: 'ki-messages', href: 'dashboard', format: formatNumber },
];
</script>

<template>
    <PanelShell :payload="payload">
        <div class="waflow-grid waflow-grid-3 mb-5">
            <a v-for="card in cards" :key="card.key" class="kt-card waflow-metric-card" :href="pages[card.href]">
                <div class="kt-card-content p-5 flex items-center gap-4">
                    <span class="inline-flex size-11 items-center justify-center rounded-lg bg-accent text-primary"><i class="ki-filled text-xl" :class="card.icon"></i></span>
                    <div><div class="text-sm text-muted-foreground">{{ card.label }}</div><div class="mt-1 text-2xl font-semibold text-mono">{{ card.format(pageData.metrics?.[card.key]) }}</div></div>
                </div>
            </a>
        </div>

        <div class="waflow-grid waflow-grid-2">
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Empresas recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.companies">Ver empresas</a></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Empresa</th><th>Plano</th><th>Status</th></tr></thead>
                    <tbody><tr v-for="company in pageData.recentWorkspaces" :key="company.uuid"><td><div class="font-medium text-mono">{{ company.name }}</div><div class="text-xs text-muted-foreground">{{ company.owner_email }}</div></td><td>{{ company.plan || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(company.status)}`">{{ statusLabel(company.status) }}</span></td></tr><tr v-if="!pageData.recentWorkspaces?.length"><td colspan="3"><PanelEmptyState icon="ki-office-bag" title="Nenhuma empresa" description="Os novos workspaces aparecerão aqui." /></td></tr></tbody>
                </table></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Parceiros recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.partners">Ver parceiros</a></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Parceiro</th><th>Clientes</th><th>Comissão</th></tr></thead>
                    <tbody><tr v-for="partner in pageData.recentPartners" :key="partner.uuid"><td><div class="font-medium text-mono">{{ partner.name }}</div><div class="text-xs text-muted-foreground">{{ partner.owner_email }}</div></td><td>{{ partner.customers_count }}</td><td>{{ partner.commission_label }}</td></tr><tr v-if="!pageData.recentPartners?.length"><td colspan="3"><PanelEmptyState icon="ki-shop" title="Nenhum parceiro" description="Cadastre o primeiro revendedor da plataforma." /></td></tr></tbody>
                </table></div></div>
            </div>
        </div>
    </PanelShell>
</template>
