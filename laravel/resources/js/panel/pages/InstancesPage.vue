<script setup>
import { computed } from 'vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const qrInstance = computed(() => pageData.value.instances?.find((instance) => instance.qr_code));
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Instâncias WhatsApp</h3><p class="kt-card-description">Cada instância mantém uma sessão isolada no workspace.</p></div></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Sessão</th><th>Telefone</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="instance in pageData.instances" :key="instance.uuid">
                            <td class="font-medium text-mono">{{ instance.name }}</td><td><code class="text-xs">{{ instance.session_name }}</code></td><td>{{ instance.phone_number || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ instance.status_label }}</span></td>
                            <td><div class="flex justify-end gap-2">
                                <form :action="instance.connect_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit"><i class="ki-filled ki-scan-barcode"></i>Conectar</button></form>
                                <form :action="instance.disconnect_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon" type="submit" aria-label="Desconectar"><i class="ki-filled ki-disconnect"></i></button></form>
                            </div></td>
                        </tr>
                        <tr v-if="!pageData.instances?.length"><td colspan="5" class="text-center text-secondary-foreground py-10">Nenhuma instância criada.</td></tr>
                    </tbody>
                </table></div></div>
            </div>

            <div class="waflow-card-stack">
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Nova instância</h3></div>
                    <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('instances').store" method="post">
                        <input name="_token" type="hidden" :value="csrfToken">
                        <div class="grid gap-1"><label class="kt-form-label" for="name">Nome</label><input id="name" class="kt-input" name="name" placeholder="Atendimento comercial" required></div>
                        <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar instância</button>
                    </form>
                </div>
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Leitura de QR</h3></div>
                    <div class="kt-card-content p-5">
                        <div v-if="qrInstance" class="border border-border rounded-lg p-4 text-center"><img class="mx-auto size-56 max-w-full object-contain" :src="qrInstance.qr_code" :alt="`QR Code da instância ${qrInstance.name}`"><div class="text-sm font-medium text-mono mt-3">{{ qrInstance.name }}</div></div>
                        <div v-else class="border border-dashed border-border rounded-lg p-6 text-center"><i class="ki-filled ki-scan-barcode text-4xl text-muted-foreground"></i><div class="text-sm font-medium text-mono mt-3">Nenhum QR pendente</div></div>
                    </div>
                </div>
            </div>
        </section>
    </PanelShell>
</template>
