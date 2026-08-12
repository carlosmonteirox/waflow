<script setup>
import PanelShell from '../PanelShell.vue';
import { badgeVariant, confirmSubmit } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><h3 class="kt-card-title">Webhooks</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>URL</th><th>Eventos</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="webhook in pageData.webhooks" :key="webhook.id"><td class="font-medium text-mono">{{ webhook.name }}</td><td class="max-w-[280px] truncate">{{ webhook.url }}</td><td>{{ webhook.events_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(webhook.status)}`">{{ webhook.status }}</span></td><td><form v-if="permissions.manage_integrations" class="flex justify-end" :action="webhook.delete_url" method="post" @submit="confirmSubmit($event, 'Remover este webhook?')"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="submit" aria-label="Remover webhook"><i class="ki-filled ki-trash"></i></button></form></td></tr>
                        <tr v-if="!pageData.webhooks?.length"><td colspan="5" class="text-center text-secondary-foreground py-10">Nenhum webhook configurado.</td></tr>
                    </tbody>
                </table></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Novo webhook</h3></div>
                <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('webhooks').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="name">Nome</label><input id="name" class="kt-input" name="name" placeholder="CRM principal" required></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="url">URL</label><input id="url" class="kt-input" name="url" placeholder="https://app.exemplo.com/webhooks/waflow" type="url" required></div>
                    <div class="grid gap-2"><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.received" checked><span class="kt-checkbox-label">message.received</span></label><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="session.status"><span class="kt-checkbox-label">session.status</span></label><label class="kt-label"><input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.failed"><span class="kt-checkbox-label">message.failed</span></label></div>
                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar webhook</button>
                </form>
                <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner, admin ou developer pode criar webhooks.</div>
            </div>
        </section>
    </PanelShell>
</template>
