<script setup>
import { computed, ref, watch } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, confirmSubmit, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const instances = computed(() => pageData.value.instances || []);
const selectedQrUuid = ref(new URL(window.location.href).searchParams.get('qr'));
const qrInstance = computed(() => instances.value.find((instance) => instance.uuid === selectedQrUuid.value));
const table = useCollectionTable(instances, {
    searchKeys: ['name', 'session_name', 'phone_number', 'status'],
    sortKey: 'name',
});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.name));
const qrModalOpen = ref(Boolean(qrInstance.value));

function openQrModal(instance) {
    selectedQrUuid.value = instance.uuid;
    qrModalOpen.value = true;
    const url = new URL(window.location.href);
    url.searchParams.set('qr', instance.uuid);
    window.history.replaceState(null, '', url);
}

watch(qrModalOpen, (open) => {
    if (open) return;

    const url = new URL(window.location.href);
    url.searchParams.delete('qr');
    window.history.replaceState(null, '', url);
    selectedQrUuid.value = null;
});
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-plus"></i>Nova instância</button>
        </template>
        <section>
            <div class="kt-card">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Instâncias WhatsApp</h3><p class="kt-card-description">Cada instância mantém uma sessão isolada no workspace.</p></div></div>
                <PanelTableToolbar v-model="table.query.value" v-model:status="table.status.value" :statuses="table.statusOptions.value" :count="table.rows.value.length" :total="table.sourceRows.value.length" placeholder="Buscar instância, sessão ou telefone" @reset="table.resetFilters" />
                <div class="kt-card-table"><div class="waflow-table-wrap waflow-table-fit"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Sessão" field="session_name" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Telefone" field="phone_number" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="table.sortKey.value" :direction="table.sortDirection.value" @sort="table.toggleSort" /></th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="instance in table.rows.value" :key="instance.uuid">
                            <td class="font-medium text-mono">{{ instance.name }}</td><td><code class="text-xs">{{ instance.session_name }}</code></td><td>{{ instance.phone_number || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ statusLabel(instance.status) }}</span></td>
                            <td class="waflow-actions-column"><div class="flex justify-end gap-2">
                                <button v-if="instance.qr_code && instance.status !== 'connected'" class="kt-btn kt-btn-sm kt-btn-outline" type="button" @click="openQrModal(instance)"><i class="ki-filled ki-scan-barcode"></i>Conectar</button>
                                <form v-else-if="instance.status !== 'connected'" :action="instance.connect_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit"><i class="ki-filled ki-scan-barcode"></i>Conectar</button></form>
                                <form :action="instance.disconnect_url" method="post" @submit="confirmSubmit($event, `Desconectar a instância ${instance.name}?`)"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon waflow-action-destructive" type="submit" aria-label="Desconectar instância" title="Desconectar instância"><i class="ki-filled ki-disconnect"></i></button></form>
                            </div></td>
                        </tr>
                        <tr v-if="!table.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-whatsapp" :title="table.sourceRows.value.length ? 'Nenhuma instância encontrada' : 'Nenhuma instância criada'" :description="table.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Crie uma instância e conecte o WhatsApp por QR Code.'" :action-button="!table.sourceRows.value.length" :action-label="table.sourceRows.value.length ? '' : 'Criar instância'" :resettable="Boolean(table.sourceRows.value.length)" @action="createModalOpen = true" @reset="table.resetFilters" /></td></tr>
                    </tbody>
                </table></div></div>
            </div>

        </section>

        <PanelModal v-model="createModalOpen" title="Nova instância" description="Crie uma sessão isolada para conectar um número do WhatsApp.">
            <form class="grid gap-4" :action="routeGroup('instances').store" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="instance-name">Nome</label><input id="instance-name" class="kt-input" name="name" placeholder="Atendimento comercial" required data-modal-autofocus></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Criar instância</button></div>
            </form>
        </PanelModal>

        <PanelModal v-model="qrModalOpen" title="Conectar WhatsApp" :description="qrInstance ? `Instância ${qrInstance.name}` : ''">
            <div v-if="qrInstance?.qr_code" class="waflow-qr-reader">
                <div class="waflow-qr-frame">
                    <img :src="qrInstance.qr_code" :alt="`QR Code da instância ${qrInstance.name}`">
                </div>
                <div class="waflow-qr-instructions">
                    <div class="flex items-center justify-between gap-3">
                        <strong>{{ qrInstance.name }}</strong>
                        <span class="kt-badge kt-badge-light kt-badge-warning">Aguardando leitura</span>
                    </div>
                    <p>Abra o WhatsApp no celular e siga os passos:</p>
                    <ol>
                        <li><span>1</span>Acesse <strong>Aparelhos conectados</strong>.</li>
                        <li><span>2</span>Toque em <strong>Conectar um aparelho</strong>.</li>
                        <li><span>3</span>Aponte a câmera para este QR Code.</li>
                    </ol>
                </div>
                <div class="waflow-modal-actions">
                    <button class="kt-btn kt-btn-outline" type="button" @click="qrModalOpen = false">Fechar</button>
                    <form :action="qrInstance.connect_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-primary" type="submit"><i class="ki-filled ki-arrows-circle"></i>Gerar novo QR</button></form>
                </div>
            </div>
            <div v-else-if="qrInstance?.status === 'connected'" class="grid gap-4">
                <PanelEmptyState icon="ki-check-circle" title="Instância conectada" description="O WhatsApp desta instância já está pronto para enviar e receber mensagens." />
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-primary" type="button" @click="qrModalOpen = false">Concluir</button></div>
            </div>
            <div v-else-if="qrInstance" class="grid gap-4">
                <PanelEmptyState icon="ki-scan-barcode" title="QR Code ainda não disponível" description="Tente gerar o código novamente para continuar a conexão." />
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="qrModalOpen = false">Fechar</button><form :action="qrInstance.connect_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-primary" type="submit">Tentar novamente</button></form></div>
            </div>
        </PanelModal>
    </PanelShell>
</template>
