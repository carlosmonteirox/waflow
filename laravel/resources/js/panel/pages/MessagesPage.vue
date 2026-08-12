<script setup>
import PanelShell from '../PanelShell.vue';
import { badgeVariant } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Eventos de mensagens</h3><p class="kt-card-description">Base consumida por filas, webhooks e relatórios.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.messages?.total || 0 }} eventos</span></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>UUID</th><th>Direção</th><th>Contato</th><th>Status</th><th>Instância</th><th>Data</th></tr></thead>
                    <tbody><tr v-for="message in pageData.messages?.items" :key="message.uuid"><td><code class="text-xs">{{ message.uuid }}</code></td><td>{{ message.direction }}</td><td>{{ message.to || message.from || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ message.status }}</span></td><td>{{ message.instance_name || '-' }}</td><td>{{ message.created_at_label }}</td></tr><tr v-if="!pageData.messages?.items?.length"><td colspan="6" class="text-center text-secondary-foreground py-10">Nenhuma mensagem registrada.</td></tr></tbody>
                </table></div></div>
                <div v-if="pageData.messages?.links?.length" class="kt-card-footer"><div class="flex flex-wrap gap-2"><a v-for="link in pageData.messages.links" :key="`${link.label}-${link.url}`" class="kt-btn kt-btn-sm" :class="link.active ? 'kt-btn-primary' : 'kt-btn-outline'" :href="link.url || '#'" :aria-disabled="!link.url">{{ link.label }}</a></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Enviar mensagem</h3></div>
                <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('messages').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label">Instância conectada</label><select class="kt-input" name="instance_uuid" required><option value="">Selecione</option><option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid" :disabled="instance.status !== 'connected'">{{ instance.name }} — {{ instance.status_label }}</option></select></div>
                    <div class="grid gap-1"><label class="kt-form-label">Destino</label><input class="kt-input" name="to" placeholder="5562999999999" required></div>
                    <div class="grid gap-1"><label class="kt-form-label">Texto</label><textarea class="kt-input min-h-28" name="text" maxlength="4096" required></textarea></div>
                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Adicionar à fila</button>
                </form>
            </div>
        </section>
    </PanelShell>
</template>
