<script setup>
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <div class="waflow-grid waflow-grid-3 mb-5">
            <div class="kt-card"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">MRR da carteira</div><div class="mt-2 text-3xl font-semibold text-mono">{{ pageData.summary?.mrr_label }}</div></div></div>
            <div class="kt-card"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">Percentual de comissão</div><div class="mt-2 text-3xl font-semibold text-mono">{{ pageData.summary?.commission_percent }}%</div></div></div>
            <div class="kt-card"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">Comissão estimada</div><div class="mt-2 text-3xl font-semibold text-mono">{{ pageData.summary?.commission_label }}</div><div class="mt-1 text-xs text-muted-foreground">{{ pageData.summary?.active_subscriptions }} assinaturas elegíveis</div></div></div>
        </div>
        <div class="kt-alert kt-alert-primary mb-5"><i class="ki-filled ki-information-2"></i><div><div class="kt-alert-title">Base de cálculo comercial</div><p class="text-sm">Os valores são estimativas mensais sobre assinaturas ativas ou em período de teste. O fechamento e pagamento da comissão dependem da conciliação financeira.</p></div></div>
        <div class="kt-card"><div class="kt-card-header"><div><h3 class="kt-card-title">Detalhamento por cliente</h3><p class="kt-card-description">Receita contratada e comissão estimada.</p></div></div><div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Cliente</th><th>Plano</th><th>Assinatura</th><th>Receita mensal</th><th>Percentual</th><th>Comissão</th></tr></thead><tbody><tr v-for="customer in pageData.customers" :key="customer.uuid"><td><div class="font-medium text-mono">{{ customer.name }}</div><div class="text-xs text-muted-foreground">{{ customer.owner_email }}</div></td><td>{{ customer.plan || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(customer.subscription_status)}`">{{ statusLabel(customer.subscription_status) }}</span></td><td>{{ customer.monthly_price_label }}</td><td>{{ customer.commission_percent }}%</td><td class="font-medium text-mono">{{ customer.commission_label }}</td></tr><tr v-if="!pageData.customers?.length"><td colspan="6"><PanelEmptyState icon="ki-chart-line-up" title="Sem receita atribuída" description="Os valores aparecerão quando houver clientes cadastrados pelo parceiro." /></td></tr></tbody></table></div></div></div>
    </PanelShell>
</template>
