<script setup>
import PanelShell from '../PanelShell.vue';
import { confirmSubmit } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Chaves emitidas</h3><p class="kt-card-description">Use Bearer token ou header X-Waflow-Key.</p></div></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Prefixo</th><th>Permissões</th><th>Último uso</th><th class="text-end">Ações</th></tr></thead>
                    <tbody><tr v-for="apiKey in pageData.apiKeys" :key="apiKey.id"><td class="font-medium text-mono">{{ apiKey.name }}</td><td><code class="text-xs">{{ apiKey.token_prefix }}</code></td><td>{{ apiKey.abilities_label }}</td><td>{{ apiKey.last_used_human || '-' }}</td><td><form v-if="permissions.manage_integrations" class="flex justify-end" :action="apiKey.delete_url" method="post" @submit="confirmSubmit($event, 'Revogar esta chave de API?')"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="submit" aria-label="Remover chave"><i class="ki-filled ki-trash"></i></button></form></td></tr><tr v-if="!pageData.apiKeys?.length"><td colspan="5" class="text-center text-secondary-foreground py-10">Nenhuma chave de API emitida.</td></tr></tbody>
                </table></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Nova chave</h3></div>
                <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('apiKeys').store" method="post"><input name="_token" type="hidden" :value="csrfToken"><div class="grid gap-1"><label class="kt-form-label" for="name">Nome</label><input id="name" class="kt-input" name="name" placeholder="Integração ERP" required></div><button class="kt-btn kt-btn-primary justify-center" type="submit">Emitir chave</button></form>
                <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Seu perfil não pode emitir chaves de API.</div>
            </div>
        </section>
    </PanelShell>
</template>
