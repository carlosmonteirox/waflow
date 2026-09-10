<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { confirmSubmit } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
const apiKeys = computed(() => pageData.value.apiKeys || []);
const table = useCollectionTable(apiKeys, {
    searchKeys: ['name', 'token_prefix', 'abilities_label'],
    sortKey: 'name',
});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.name));
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button v-if="permissions.manage_integrations" class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-key"></i>Emitir chave</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Chaves emitidas</h3><p class="kt-card-description">Use Bearer token ou header X-Waflow-Key.</p></div></div>
                <PanelTableToolbar v-model="table.query.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar chave ou permissão" @reset="table.resetFilters" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Prefixo" field="token_prefix" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th>Permissões</th><th>Último uso</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="apiKey in table.rows.value" :key="apiKey.id"><td class="font-medium text-mono">{{ apiKey.name }}</td><td><code class="text-xs">{{ apiKey.token_prefix }}</code></td><td><span class="waflow-cell-preview" :title="apiKey.abilities_label">{{ apiKey.abilities_label }}</span></td><td>{{ apiKey.last_used_human || 'Nunca utilizada' }}</td><td class="waflow-actions-column"><form v-if="permissions.manage_integrations" class="flex justify-end" :action="apiKey.delete_url" method="post" @submit="confirmSubmit($event, `Revogar a chave ${apiKey.name}?`)"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost waflow-action-destructive" type="submit" aria-label="Revogar chave" title="Revogar chave"><i class="ki-filled ki-trash"></i></button></form></td></tr>
                        <tr v-if="!table.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-key" :title="table.sourceRows.value.length ? 'Nenhuma chave encontrada' : 'Nenhuma chave de API emitida'" :description="table.sourceRows.value.length ? 'Tente buscar por outro nome ou permissão.' : 'Emita uma chave para autenticar integrações externas.'" :action-button="!table.sourceRows.value.length && permissions.manage_integrations" :action-label="table.sourceRows.value.length || !permissions.manage_integrations ? '' : 'Emitir chave'" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                    </tbody>
                </table></div></div>
            </div>
        </section>

        <PanelModal v-model="createModalOpen" title="Nova chave de API" description="O token completo será exibido uma única vez após a emissão.">
            <form v-if="permissions.manage_integrations" class="grid gap-4" :action="routeGroup('apiKeys').store" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="api-key-name">Nome</label><input id="api-key-name" class="kt-input" name="name" placeholder="Integração ERP" required data-modal-autofocus></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Emitir chave</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
