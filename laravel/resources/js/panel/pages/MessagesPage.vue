<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelPagination from '../PanelPagination.vue';
import PanelServerTableToolbar from '../PanelServerTableToolbar.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import { badgeVariant, directionLabel, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, pages, routeGroup } = usePanelContext(props);
const connectedInstances = computed(() => (pageData.value.instances || []).filter((instance) => instance.status === 'connected'));
const filters = computed(() => pageData.value.filters || {});
const statusOptions = computed(() => (pageData.value.statuses || []).map((value) => ({ value, label: statusLabel(value) })));
const sendModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.instance_uuid || props.payload.flash?.fieldErrors?.to || props.payload.flash?.fieldErrors?.text));

function sortUrl(field) {
    const url = new URL(window.location.href);
    const nextDirection = filters.value.sort === field && filters.value.direction === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', nextDirection);
    url.searchParams.delete('page');

    return url.toString();
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button class="kt-btn kt-btn-primary" type="button" @click="sendModalOpen = true"><i class="ki-filled ki-send"></i>Enviar mensagem</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Eventos de mensagens</h3><p class="kt-card-description">Base consumida por filas, webhooks e relatórios.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.messages?.total || 0 }} eventos</span></div>
                <PanelServerTableToolbar :action="pages.messages" :query="filters.search" :status="filters.status" :statuses="statusOptions" placeholder="Buscar por contato, telefone, status ou instância" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>UUID</th><th><PanelSortButton label="Direção" field="direction" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('direction')" /></th><th>Contato</th><th><PanelSortButton label="Status" field="status" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('status')" /></th><th>Instância</th><th>Conteúdo</th><th><PanelSortButton label="Data" field="created_at" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('created_at')" /></th></tr></thead>
                    <tbody>
                        <tr v-for="message in pageData.messages?.items" :key="message.uuid">
                            <td><code class="text-xs">{{ message.uuid }}</code></td>
                            <td>{{ directionLabel(message.direction) }}</td>
                            <td>{{ message.contact_name || message.to || message.from || '-' }}</td>
                            <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ statusLabel(message.status) }}</span></td>
                            <td>{{ message.instance_name || '-' }}</td>
                            <td><span class="waflow-cell-preview" :title="message.body || ''">{{ message.body || '-' }}</span></td>
                            <td>{{ message.created_at_label }}</td>
                        </tr>
                        <tr v-if="!pageData.messages?.items?.length"><td colspan="7"><PanelEmptyState icon="ki-messages" :title="filters.search || filters.status ? 'Nenhum resultado encontrado' : 'Nenhuma mensagem registrada'" :description="filters.search || filters.status ? 'Tente remover os filtros ou buscar por outro termo.' : 'As mensagens enviadas e recebidas aparecerão aqui.'" :action-href="filters.search || filters.status ? pages.messages : ''" :action-button="!filters.search && !filters.status" :action-label="filters.search || filters.status ? 'Limpar filtros' : 'Enviar mensagem'" @action="sendModalOpen = true" /></td></tr>
                    </tbody>
                </table></div></div>
                <div class="kt-card-footer"><PanelPagination v-if="pageData.messages?.total" :pagination="pageData.messages" label="eventos" /></div>
            </div>
        </section>

        <PanelModal v-model="sendModalOpen" title="Enviar mensagem" description="Adicione uma nova mensagem à fila de envio.">
                <form class="grid gap-4" :action="routeGroup('messages').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div v-if="!connectedInstances.length" class="kt-alert kt-alert-warning text-sm"><span>Conecte uma instância antes de enviar mensagens.</span><a class="font-medium underline" :href="pages.instances">Abrir instâncias</a></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="message-instance">Instância conectada</label><select id="message-instance" class="kt-input" name="instance_uuid" required data-modal-autofocus><option value="">Selecione</option><option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid" :disabled="instance.status !== 'connected'">{{ instance.name }} — {{ statusLabel(instance.status) }}</option></select></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="message-recipient">Destino</label><input id="message-recipient" class="kt-input" name="to" inputmode="tel" autocomplete="tel" placeholder="5562999999999" required><p class="text-xs text-muted-foreground">Informe o número com código do país e DDD.</p></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="message-text">Texto</label><textarea id="message-text" class="kt-input min-h-28" name="text" maxlength="4096" required></textarea></div>
                    <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="sendModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit" :disabled="!connectedInstances.length">Adicionar à fila</button></div>
                </form>
        </PanelModal>
    </PanelShell>
</template>
