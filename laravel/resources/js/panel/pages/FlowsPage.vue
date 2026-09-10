<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, statusLabel, triggerLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const flows = computed(() => pageData.value.flows || []);
const table = useCollectionTable(flows, {
    searchKeys: ['name', 'trigger_type', 'status'],
    sortKey: 'name',
});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.name || props.payload.flash?.fieldErrors?.trigger_type));
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-plus"></i>Novo fluxo</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Fluxos</h3></div>
                <PanelTableToolbar v-model="table.query.value" v-model:status="table.status.value" :statuses="table.statusOptions.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar fluxo ou gatilho" @reset="table.resetFilters" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Gatilho" field="trigger_type" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th>Última execução</th><th>Execuções</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="flow in table.rows.value" :key="flow.uuid">
                            <td class="font-medium text-mono">{{ flow.name }}</td><td>{{ triggerLabel(flow.trigger_type) }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(flow.status)}`">{{ statusLabel(flow.status) }}</span></td><td>{{ flow.last_run_human || '-' }}</td><td>{{ flow.run_count || 0 }} / {{ flow.failure_count || 0 }} falhas</td>
                            <td class="waflow-actions-column"><div class="flex justify-end gap-2"><form :action="flow.toggle_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ flow.status === 'active' ? 'Pausar' : 'Ativar' }}</button></form><form :action="flow.test_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Testar</button></form></div></td>
                        </tr>
                        <tr v-if="!table.rows.value.length"><td colspan="6"><PanelEmptyState icon="ki-route" :title="table.sourceRows.value.length ? 'Nenhum fluxo encontrado' : 'Nenhum fluxo criado'" :description="table.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Crie um fluxo para automatizar respostas e encaminhamentos.'" :action-button="!table.sourceRows.value.length" :action-label="table.sourceRows.value.length ? '' : 'Criar fluxo'" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                    </tbody>
                </table></div></div>
            </div>

            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Execuções recentes</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Fluxo</th><th>Gatilho</th><th>Status</th><th>Data</th><th>Erro</th></tr></thead>
                    <tbody>
                        <tr v-for="execution in pageData.executions || []" :key="execution.uuid"><td>{{ execution.flow_name }}</td><td>{{ triggerLabel(execution.trigger_type) }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(execution.status)}`">{{ statusLabel(execution.status) }}</span></td><td>{{ execution.created_at_label }}</td><td><details v-if="execution.error" class="waflow-cell-details"><summary>Ver erro</summary><pre>{{ execution.error }}</pre></details><span v-else>-</span></td></tr>
                        <tr v-if="!pageData.executions?.length"><td colspan="5"><PanelEmptyState icon="ki-chart-line-up" title="Nenhuma execução registrada" description="As execuções aparecerão aqui após um fluxo ser ativado ou testado." /></td></tr>
                    </tbody>
                </table></div></div>
            </div>
        </section>

        <PanelModal v-model="createModalOpen" title="Novo fluxo" description="Configure o gatilho, a condição e a resposta da automação." size="lg">
            <form class="grid gap-4" :action="routeGroup('flows').store" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="flow-name">Nome</label><input id="flow-name" class="kt-input" name="name" placeholder="Boas-vindas" required data-modal-autofocus></div>
                <div class="grid gap-1"><label class="kt-form-label" for="flow-trigger">Gatilho</label><select id="flow-trigger" class="kt-input" name="trigger_type" required><option value="message_received">Mensagem recebida</option><option value="message_failed">Mensagem com falha</option><option value="instance_connected">Instância conectada</option></select></div>
                <div class="grid gap-1"><label class="kt-form-label" for="flow-status">Status inicial</label><select id="flow-status" class="kt-input" name="status"><option value="draft">Rascunho</option><option value="active">Ativo</option></select></div>
                <fieldset class="waflow-form-section"><legend>Condição opcional</legend><div class="grid gap-1"><label class="kt-form-label" for="condition-field">Campo</label><input id="condition-field" class="kt-input" name="condition_field" placeholder="trigger.body"></div><div class="grid gap-1"><label class="kt-form-label" for="condition-operator">Operador</label><select id="condition-operator" class="kt-input" name="condition_operator"><option value="contains">Contém</option><option value="equals">Igual</option><option value="not_equals">Diferente</option><option value="starts_with">Começa com</option><option value="ends_with">Termina com</option><option value="exists">Existe</option></select></div><div class="grid gap-1"><label class="kt-form-label" for="condition-value">Valor esperado</label><input id="condition-value" class="kt-input" name="condition_value"></div></fieldset>
                <fieldset class="waflow-form-section"><legend>Resposta automática</legend><div class="grid gap-1"><label class="kt-form-label" for="flow-instance">Instância</label><select id="flow-instance" class="kt-input" name="instance_uuid"><option value="">Usar instância do evento</option><option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid">{{ instance.name }}</option></select></div><div class="grid gap-1"><label class="kt-form-label" for="flow-recipient">Destinatário</label><input id="flow-recipient" class="kt-input" name="recipient" placeholder="Vazio usa o remetente do evento"></div><div class="grid gap-1"><label class="kt-form-label" for="flow-message">Mensagem</label><textarea id="flow-message" class="kt-input min-h-24" name="message_text" placeholder="Olá! Recebemos sua mensagem."></textarea></div></fieldset>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Criar fluxo</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
