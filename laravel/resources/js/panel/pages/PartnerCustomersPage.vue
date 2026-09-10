<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelPagination from '../PanelPagination.vue';
import PanelServerTableToolbar from '../PanelServerTableToolbar.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, copyText, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, pages } = usePanelContext(props);
const filters = computed(() => pageData.value.filters || {});
const copied = ref(false);
const statuses = [{ value: 'active', label: 'Ativa' }, { value: 'suspended', label: 'Suspensa' }];

async function copyReferral() {
    await copyText(pageData.value.referral_url);
    copied.value = true;
    window.setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions><button class="kt-btn kt-btn-primary" type="button" @click="copyReferral"><i class="ki-filled" :class="copied ? 'ki-check' : 'ki-copy'"></i>{{ copied ? 'Link copiado' : 'Convidar nova empresa' }}</button></template>
        <div class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Carteira de clientes</h3><p class="kt-card-description">Empresas originadas pelo seu link de parceiro.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.customers?.total || 0 }} clientes</span></div>
            <PanelServerTableToolbar :action="pages.customers" :query="filters.search" :status="filters.status" :statuses="statuses" placeholder="Buscar empresa ou email do proprietário" />
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Empresa</th><th>Proprietário</th><th>Plano</th><th>Assinatura</th><th>Estrutura</th><th>Receita mensal</th><th>Comissão</th><th>Indicação</th></tr></thead><tbody><tr v-for="customer in pageData.customers?.items" :key="customer.uuid"><td><div class="font-medium text-mono">{{ customer.name }}</div><span class="kt-badge kt-badge-light mt-1" :class="`kt-badge-${badgeVariant(customer.status)}`">{{ statusLabel(customer.status) }}</span></td><td><div>{{ customer.owner }}</div><div class="text-xs text-muted-foreground">{{ customer.owner_email }}</div></td><td>{{ customer.plan || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(customer.subscription_status)}`">{{ statusLabel(customer.subscription_status) }}</span></td><td>{{ customer.members_count }} membros · {{ customer.instances_count }} instâncias</td><td>{{ customer.monthly_price_label }}</td><td>{{ customer.commission_percent }}% · {{ customer.commission_label }}</td><td>{{ customer.referred_at_label || '-' }}</td></tr><tr v-if="!pageData.customers?.items?.length"><td colspan="8"><PanelEmptyState icon="ki-office-bag" :title="filters.search || filters.status ? 'Nenhum cliente encontrado' : 'Nenhum cliente indicado'" :description="filters.search || filters.status ? 'Tente remover os filtros.' : 'Compartilhe seu link comercial para iniciar a carteira.'" :action-button="!filters.search && !filters.status" action-label="Copiar link" @action="copyReferral" /></td></tr></tbody></table></div></div>
            <div class="kt-card-footer"><PanelPagination v-if="pageData.customers?.total" :pagination="pageData.customers" label="clientes" /></div>
        </div>
    </PanelShell>
</template>
