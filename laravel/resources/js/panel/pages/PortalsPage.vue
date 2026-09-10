<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, confirmSubmit, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
const portals = computed(() => pageData.value.portals || []);
const table = useCollectionTable(portals, {
    searchKeys: ['name', 'domain', 'url', 'status'],
    sortKey: 'name',
});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.name || props.payload.flash?.fieldErrors?.url));
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button v-if="permissions.manage_integrations" class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-plus"></i>Adicionar site/domínio</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Sites e domínios autorizados</h3><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.portals?.length || 0 }} cadastrados</span></div>
                <PanelTableToolbar v-model="table.query.value" v-model:status="table.status.value" :statuses="table.statusOptions.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar site, nome ou domínio" @reset="table.resetFilters" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Domínio" field="domain" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th>Verificação</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="portal in table.rows.value" :key="portal.uuid">
                            <td><a class="font-medium text-primary" :href="portal.url" target="_blank" rel="noopener">{{ portal.name }} <i class="ki-filled ki-arrow-up-right text-xs"></i></a></td><td>{{ portal.domain }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(portal.status)}`">{{ statusLabel(portal.status) }}</span></td><td>{{ portal.verified_at_label || '-' }}</td>
                            <td class="waflow-actions-column"><div v-if="permissions.manage_integrations" class="flex justify-end gap-2"><form v-if="portal.status !== 'verified'" class="flex gap-2" :action="portal.verify_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><label class="sr-only" :for="`portal-token-${portal.uuid}`">Token de verificação de {{ portal.name }}</label><input :id="`portal-token-${portal.uuid}`" class="kt-input kt-input-sm max-w-44" name="verification_token" placeholder="Token" required><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Verificar</button></form><form :action="portal.delete_url" method="post" @submit="confirmSubmit($event, `Remover o site/domínio ${portal.name}?`)"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-ghost waflow-action-destructive" type="submit">Remover</button></form></div></td>
                        </tr>
                        <tr v-if="!table.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-map" :title="table.sourceRows.value.length ? 'Nenhum site ou domínio encontrado' : 'Nenhum site ou domínio cadastrado'" :description="table.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Adicione um site autorizado a operar com este workspace.'" :action-button="!table.sourceRows.value.length && permissions.manage_integrations" :action-label="table.sourceRows.value.length || !permissions.manage_integrations ? '' : 'Adicionar site/domínio'" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                    </tbody>
                </table></div></div>
            </div>
        </section>

        <PanelModal v-model="createModalOpen" title="Adicionar site/domínio" description="Autorize um site a operar com este workspace.">
            <form v-if="permissions.manage_integrations" class="grid gap-4" :action="routeGroup('portals').store" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="portal-name">Nome de identificação</label><input id="portal-name" class="kt-input" name="name" placeholder="Site principal" required data-modal-autofocus></div>
                <div class="grid gap-1"><label class="kt-form-label" for="portal-url">URL</label><input id="portal-url" class="kt-input" name="url" type="url" inputmode="url" autocomplete="url" placeholder="https://portal.com.br" required></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Adicionar site/domínio</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
