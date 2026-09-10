<script setup>
import { ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, copyText, formatNumber, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, pages } = usePanelContext(props);
const copied = ref(false);

function money(cents) {
    return Number(cents || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function copyReferral() {
    await copyText(pageData.value.referral_url);
    copied.value = true;
    window.setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions><button class="kt-btn kt-btn-primary" type="button" @click="copyReferral"><i class="ki-filled" :class="copied ? 'ki-check' : 'ki-copy'"></i>{{ copied ? 'Link copiado' : 'Copiar link de venda' }}</button></template>
        <div class="waflow-grid waflow-grid-4 mb-5">
            <a class="kt-card waflow-metric-card" :href="pages.customers"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">Clientes indicados</div><div class="mt-2 text-3xl font-semibold text-mono">{{ formatNumber(pageData.metrics?.customers) }}</div></div></a>
            <a class="kt-card waflow-metric-card" :href="pages.customers"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">Clientes ativos</div><div class="mt-2 text-3xl font-semibold text-mono">{{ formatNumber(pageData.metrics?.active_customers) }}</div></div></a>
            <a class="kt-card waflow-metric-card" :href="pages.finance"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">MRR da carteira</div><div class="mt-2 text-3xl font-semibold text-mono">{{ money(pageData.metrics?.mrr_cents) }}</div></div></a>
            <a class="kt-card waflow-metric-card" :href="pages.finance"><div class="kt-card-content p-5"><div class="text-sm text-muted-foreground">Comissão estimada</div><div class="mt-2 text-3xl font-semibold text-mono">{{ money(pageData.metrics?.commission_cents) }}</div></div></a>
        </div>

        <div class="kt-card mb-5"><div class="kt-card-header"><div><h3 class="kt-card-title">Seu link comercial</h3><p class="kt-card-description">Toda empresa cadastrada por este endereço entra automaticamente na sua carteira.</p></div></div><div class="kt-card-content p-5"><div class="flex flex-col md:flex-row gap-2"><input class="kt-input grow font-mono text-xs" readonly :value="pageData.referral_url" aria-label="Link comercial"><button class="kt-btn kt-btn-outline" type="button" @click="copyReferral"><i class="ki-filled ki-copy"></i>Copiar link</button><a class="kt-btn kt-btn-primary" :href="pageData.referral_url" target="_blank" rel="noopener">Testar cadastro <i class="ki-filled ki-arrow-up-right"></i></a></div></div></div>

        <div class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Clientes recentes</h3><p class="kt-card-description">Últimas empresas atribuídas ao parceiro.</p></div><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.customers">Ver carteira</a></div>
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Empresa</th><th>Plano</th><th>Assinatura</th><th>Receita mensal</th><th>Comissão</th><th>Indicação</th></tr></thead><tbody><tr v-for="customer in pageData.recentCustomers" :key="customer.uuid"><td><div class="font-medium text-mono">{{ customer.name }}</div><div class="text-xs text-muted-foreground">{{ customer.owner_email }}</div></td><td>{{ customer.plan || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(customer.subscription_status)}`">{{ statusLabel(customer.subscription_status) }}</span></td><td>{{ customer.monthly_price_label }}</td><td>{{ customer.commission_label }}</td><td>{{ customer.referred_at_label || '-' }}</td></tr><tr v-if="!pageData.recentCustomers?.length"><td colspan="6"><PanelEmptyState icon="ki-office-bag" title="Nenhum cliente indicado" description="Compartilhe seu link comercial para cadastrar a primeira empresa." /></td></tr></tbody></table></div></div>
        </div>
    </PanelShell>
</template>
