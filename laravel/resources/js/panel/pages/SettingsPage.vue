<script setup>
import PanelShell from '../PanelShell.vue';
import { badgeVariant } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { csrfToken, permissions, routeGroup, plan, workspace } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2"><div class="kt-card-header"><h3 class="kt-card-title">Workspace</h3></div><form v-if="permissions.manage_settings" class="kt-card-content p-5 grid gap-4" :action="routeGroup('settings').update" method="post"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="patch"><div class="grid gap-1"><label class="kt-form-label" for="workspace-name">Nome</label><input id="workspace-name" class="kt-input" name="name" :value="workspace.name" required></div><div class="grid gap-1"><label class="kt-form-label" for="workspace-timezone">Timezone</label><input id="workspace-timezone" class="kt-input" name="timezone" :value="workspace.timezone" required></div><div class="flex justify-end"><button class="kt-btn kt-btn-primary" type="submit">Salvar alterações</button></div></form><div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode alterar o workspace.</div></div>
            <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Ambiente</h3></div><div class="kt-card-content p-5 grid gap-3 text-sm"><div class="flex justify-between gap-3"><span class="text-secondary-foreground">Workspace</span><span class="text-mono font-medium">{{ workspace.slug }}</span></div><div class="flex justify-between gap-3"><span class="text-secondary-foreground">Status</span><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(workspace.status)}`">{{ workspace.status }}</span></div><div class="flex justify-between gap-3"><span class="text-secondary-foreground">Plano</span><span class="text-mono font-medium">{{ plan?.name || 'Growth' }}</span></div></div></div>
        </section>
    </PanelShell>
</template>
