<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, confirmSubmit, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const partners = computed(() => pageData.value.partners || []);
const table = useCollectionTable(partners, { searchKeys: ['name', 'brand_name', 'owner', 'owner_email', 'referral_code', 'status'], sortKey: 'name' });
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.owner_email || props.payload.flash?.fieldErrors?.commission_percent));
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions><button class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-plus"></i>Novo parceiro</button></template>
        <div class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Rede de parceiros</h3><p class="kt-card-description">Revendedores autorizados a indicar e acompanhar clientes.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ partners.length }} parceiros</span></div>
            <PanelTableToolbar v-model="table.query.value" v-model:status="table.status.value" :statuses="table.statusOptions.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar parceiro, proprietário ou código" @reset="table.resetFilters" />
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                <thead><tr><th>Parceiro</th><th>Proprietário</th><th>Código</th><th>Clientes</th><th>MRR</th><th>Comissão</th><th>Status</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                <tbody>
                    <tr v-for="partner in table.rows.value" :key="partner.uuid"><td><div class="font-medium text-mono">{{ partner.name }}</div><div class="text-xs text-muted-foreground">{{ partner.brand_name }}</div></td><td><div>{{ partner.owner }}</div><div class="text-xs text-muted-foreground">{{ partner.owner_email }}</div></td><td><code>{{ partner.referral_code }}</code></td><td>{{ partner.customers_count }}</td><td>{{ partner.mrr_label }}</td><td>{{ partner.commission_percent }}% · {{ partner.commission_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(partner.status)}`">{{ statusLabel(partner.status) }}</span></td><td class="waflow-actions-column"><form class="flex justify-end" :action="partner.status_url" method="post" @submit="partner.status === 'active' ? confirmSubmit($event, `Suspender o parceiro ${partner.name}?`) : null"><input name="_token" type="hidden" :value="csrfToken"><input name="status" type="hidden" :value="partner.status === 'active' ? 'suspended' : 'active'"><button class="kt-btn kt-btn-sm" :class="partner.status === 'active' ? 'kt-btn-ghost waflow-action-destructive' : 'kt-btn-outline'" type="submit">{{ partner.status === 'active' ? 'Suspender' : 'Reativar' }}</button></form></td></tr>
                    <tr v-if="!table.rows.value.length"><td colspan="8"><PanelEmptyState icon="ki-shop" :title="table.sourceRows.value.length ? 'Nenhum parceiro encontrado' : 'Nenhum parceiro cadastrado'" :description="table.sourceRows.value.length ? 'Tente remover os filtros.' : 'Cadastre uma empresa revendedora para iniciar o canal de parceiros.'" :action-button="!table.sourceRows.value.length" action-label="Novo parceiro" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                </tbody>
            </table></div></div>
        </div>

        <PanelModal v-model="createModalOpen" title="Novo parceiro" description="O proprietário precisa possuir uma conta Waflow existente.">
            <form class="grid gap-4" :action="routeGroup('admin').partnersStore" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="partner-name">Nome da empresa</label><input id="partner-name" class="kt-input" name="name" placeholder="Parceiro Regional" required data-modal-autofocus></div>
                <div class="grid gap-1"><label class="kt-form-label" for="partner-owner">Email do proprietário</label><input id="partner-owner" class="kt-input" name="owner_email" type="email" autocomplete="email" placeholder="dono@parceiro.com" required><p class="text-xs text-muted-foreground">Use o email de um usuário já cadastrado na Waflow.</p></div>
                <div class="grid gap-1"><label class="kt-form-label" for="partner-commission">Comissão mensal (%)</label><input id="partner-commission" class="kt-input" name="commission_percent" type="number" min="0" max="100" step="0.01" value="20" required></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Criar parceiro</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
