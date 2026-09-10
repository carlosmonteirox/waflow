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
const webhooks = computed(() => pageData.value.webhooks || []);
const table = useCollectionTable(webhooks, {
    searchKeys: ['name', 'url', 'events_label', 'status'],
    sortKey: 'name',
});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.name || props.payload.flash?.fieldErrors?.url || props.payload.flash?.fieldErrors?.events));
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button v-if="permissions.manage_integrations" class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-plus"></i>Novo webhook</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Webhooks</h3></div>
                <PanelTableToolbar v-model="table.query.value" v-model:status="table.status.value" :statuses="table.statusOptions.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar webhook, URL ou evento" @reset="table.resetFilters" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="URL" field="url" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th>Eventos</th><th><PanelSortButton label="Status" field="status" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="webhook in table.rows.value" :key="webhook.id"><td class="font-medium text-mono">{{ webhook.name }}</td><td><a class="waflow-cell-preview text-primary" :href="webhook.url" target="_blank" rel="noopener" :title="webhook.url">{{ webhook.url }} <i class="ki-filled ki-arrow-up-right text-xs"></i></a></td><td><span class="waflow-cell-preview" :title="webhook.events_label">{{ webhook.events_label }}</span></td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(webhook.status)}`">{{ statusLabel(webhook.status) }}</span></td><td class="waflow-actions-column"><form v-if="permissions.manage_integrations" class="flex justify-end" :action="webhook.delete_url" method="post" @submit="confirmSubmit($event, `Remover o webhook ${webhook.name}?`)"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost waflow-action-destructive" type="submit" aria-label="Remover webhook" title="Remover webhook"><i class="ki-filled ki-trash"></i></button></form></td></tr>
                        <tr v-if="!table.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-abstract-26" :title="table.sourceRows.value.length ? 'Nenhum webhook encontrado' : 'Nenhum webhook configurado'" :description="table.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Configure um callback para receber eventos do Waflow.'" :action-button="!table.sourceRows.value.length && permissions.manage_integrations" :action-label="table.sourceRows.value.length || !permissions.manage_integrations ? '' : 'Criar webhook'" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                    </tbody>
                </table></div></div>
            </div>
        </section>

        <PanelModal v-model="createModalOpen" title="Novo webhook" description="Defina o endpoint e os eventos que serão enviados.">
                <form v-if="permissions.manage_integrations" class="grid gap-4" :action="routeGroup('webhooks').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="webhook-name">Nome</label><input id="webhook-name" class="kt-input" name="name" placeholder="CRM principal" required data-modal-autofocus></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="webhook-url">URL</label><input id="webhook-url" class="kt-input" name="url" inputmode="url" autocomplete="url" placeholder="https://app.exemplo.com/webhooks/waflow" type="url" required></div>
                    <fieldset class="waflow-form-section"><legend>Eventos enviados</legend><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.received" checked><span class="kt-checkbox-label">Mensagem recebida</span></label><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="session.status"><span class="kt-checkbox-label">Status da sessão</span></label><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.failed"><span class="kt-checkbox-label">Falha no envio</span></label></fieldset>
                    <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Criar webhook</button></div>
                </form>
        </PanelModal>
    </PanelShell>
</template>
