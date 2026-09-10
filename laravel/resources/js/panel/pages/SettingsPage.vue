<script setup>
import { ref } from 'vue';
import PanelShell from '../PanelShell.vue';
import PanelTabs from '../PanelTabs.vue';
import { badgeVariant, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { csrfToken, permissions, routeGroup, plan, workspace } = usePanelContext(props);
const activeTab = ref('settings-workspace');
const tabs = [
    { id: 'settings-workspace', label: 'Workspace', icon: 'ki-setting-2' },
    { id: 'settings-environment', label: 'Ambiente', icon: 'ki-data' },
];
</script>

<template>
    <PanelShell :payload="payload">
        <PanelTabs v-model="activeTab" :tabs="tabs" label="Configurações">
            <template #settings-workspace>
                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Workspace</h3><p class="kt-card-description">Informações gerais usadas em todo o painel.</p></div></div>
                    <form v-if="permissions.manage_settings" class="kt-card-content p-5 grid gap-4" :action="routeGroup('settings').update" method="post">
                        <input name="_token" type="hidden" :value="csrfToken">
                        <input name="_method" type="hidden" value="patch">
                        <div class="grid gap-1"><label class="kt-form-label" for="workspace-name">Nome</label><input id="workspace-name" class="kt-input" name="name" :value="workspace.name" required></div>
                        <div class="grid gap-1"><label class="kt-form-label" for="workspace-timezone">Fuso horário</label><input id="workspace-timezone" class="kt-input" name="timezone" :value="workspace.timezone" autocomplete="off" required><p class="text-xs text-muted-foreground">Use um identificador IANA, como America/Sao_Paulo.</p></div>
                        <div class="flex justify-end"><button class="kt-btn kt-btn-primary" type="submit">Salvar alterações</button></div>
                    </form>
                    <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente proprietários ou administradores podem alterar o workspace.</div>
                </div>
            </template>

            <template #settings-environment>
                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Ambiente</h3><p class="kt-card-description">Identificação e estado atual do workspace.</p></div></div>
                    <div class="kt-card-content p-5 grid gap-4 text-sm">
                        <div class="flex justify-between gap-3"><span class="text-secondary-foreground">Workspace</span><span class="text-mono font-medium">{{ workspace.slug }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-secondary-foreground">Status</span><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(workspace.status)}`">{{ statusLabel(workspace.status) }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-secondary-foreground">Plano</span><span class="text-mono font-medium">{{ plan?.name || 'Growth' }}</span></div>
                        <div class="flex justify-between gap-3"><span class="text-secondary-foreground">Fuso horário</span><span class="text-mono font-medium">{{ workspace.timezone }}</span></div>
                    </div>
                </div>
            </template>
        </PanelTabs>
    </PanelShell>
</template>
