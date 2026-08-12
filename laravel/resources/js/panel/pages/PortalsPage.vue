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
                <div class="kt-card-header"><h3 class="kt-card-title">Portais vinculados</h3><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.portals?.length || 0 }} portais</span></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Domínio</th><th>Status</th><th>Verificação</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="portal in pageData.portals || []" :key="portal.uuid">
                            <td><a class="font-medium text-primary" :href="portal.url" target="_blank" rel="noopener">{{ portal.name }}</a></td><td>{{ portal.domain }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(portal.status)}`">{{ portal.status }}</span></td><td>{{ portal.verified_at_label || '-' }}</td>
                            <td><div v-if="permissions.manage_integrations" class="flex justify-end gap-2"><form v-if="portal.status !== 'verified'" class="flex gap-2" :action="portal.verify_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><input class="kt-input kt-input-sm max-w-44" name="verification_token" placeholder="Token" required><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Verificar</button></form><form :action="portal.delete_url" method="post" @submit="confirmSubmit($event, 'Remover este portal?')"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Remover</button></form></div></td>
                        </tr>
                        <tr v-if="!pageData.portals?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum portal cadastrado.</td></tr>
                    </tbody>
                </table></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Adicionar portal</h3></div>
                <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('portals').store" method="post"><input name="_token" type="hidden" :value="csrfToken"><div class="grid gap-1"><label class="kt-form-label">Nome</label><input class="kt-input" name="name" placeholder="Portal principal" required></div><div class="grid gap-1"><label class="kt-form-label">URL</label><input class="kt-input" name="url" type="url" placeholder="https://portal.com.br" required></div><button class="kt-btn kt-btn-primary justify-center" type="submit">Adicionar portal</button></form>
                <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Seu perfil tem acesso somente à consulta dos portais.</div>
            </div>
        </section>
    </PanelShell>
</template>
