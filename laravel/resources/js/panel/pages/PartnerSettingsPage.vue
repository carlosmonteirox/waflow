<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelTabs from '../PanelTabs.vue';
import { confirmSubmit, copyText, roleLabel, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const activeTab = ref('partner-branding');
const memberModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.email || props.payload.flash?.fieldErrors?.role));
const copied = ref(false);
const tabs = computed(() => [
    { id: 'partner-branding', label: 'Marca e domínio', icon: 'ki-design', },
    { id: 'partner-team', label: 'Equipe', icon: 'ki-people', count: pageData.value.members?.length || 0 },
]);

function partnerRoleLabel(role) {
    return { owner: 'Proprietário', admin: 'Administrador', sales: 'Comercial', support: 'Suporte' }[role] || roleLabel(role);
}

async function copyReferral() {
    await copyText(pageData.value.partner?.referral_url);
    copied.value = true;
    window.setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<template>
    <PanelShell :payload="payload">
        <PanelTabs v-model="activeTab" :tabs="tabs" label="Configurações do parceiro">
            <template #partner-branding>
                <div class="waflow-grid waflow-grid-3">
                    <div class="kt-card waflow-span-2">
                        <div class="kt-card-header"><div><h3 class="kt-card-title">Identidade comercial</h3><p class="kt-card-description">Informações exibidas na experiência de indicação.</p></div></div>
                        <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('partner').settingsUpdate" method="post">
                            <input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="patch">
                            <fieldset :disabled="!pageData.can_manage" class="grid gap-4">
                                <div class="grid gap-1"><label class="kt-form-label" for="partner-brand-name">Nome da marca</label><input id="partner-brand-name" class="kt-input" name="brand_name" :value="pageData.partner?.brand_name" required></div>
                                <div class="grid gap-1"><label class="kt-form-label" for="partner-domain">Domínio personalizado</label><input id="partner-domain" class="kt-input" name="custom_domain" :value="pageData.partner?.custom_domain" placeholder="app.suaempresa.com"><p class="text-xs text-muted-foreground">Informe apenas o domínio, sem https://. A ativação de DNS será feita pela administração da plataforma.</p></div>
                                <div class="grid gap-1"><label class="kt-form-label" for="partner-color">Cor principal</label><div class="flex gap-2"><input id="partner-color" class="h-10 w-14 rounded-md border border-border bg-background p-1" name="primary_color" type="color" :value="pageData.partner?.primary_color"><input class="kt-input" readonly :value="pageData.partner?.primary_color" aria-label="Código da cor"></div></div>
                            </fieldset>
                            <div v-if="pageData.can_manage" class="flex justify-end"><button class="kt-btn kt-btn-primary" type="submit">Salvar identidade</button></div>
                            <p v-else class="text-sm text-muted-foreground">Seu papel possui acesso somente para consulta.</p>
                        </form>
                    </div>
                    <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Programa comercial</h3></div><div class="kt-card-content p-5 grid gap-4"><div><div class="text-xs text-muted-foreground">Código do parceiro</div><code class="mt-1 block">{{ pageData.partner?.referral_code }}</code></div><div><div class="text-xs text-muted-foreground">Comissão contratada</div><div class="mt-1 text-2xl font-semibold text-mono">{{ pageData.partner?.commission_percent }}%</div></div><button class="kt-btn kt-btn-outline justify-center" type="button" @click="copyReferral"><i class="ki-filled" :class="copied ? 'ki-check' : 'ki-copy'"></i>{{ copied ? 'Link copiado' : 'Copiar link comercial' }}</button></div></div>
                </div>
            </template>

            <template #partner-team>
                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Equipe do parceiro</h3><p class="kt-card-description">Pessoas autorizadas a acompanhar clientes e resultados.</p></div><button v-if="pageData.can_manage" class="kt-btn kt-btn-sm kt-btn-primary" type="button" @click="memberModalOpen = true"><i class="ki-filled ki-plus"></i>Adicionar membro</button></div>
                    <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Membro</th><th>Papel</th><th>Status</th><th>Entrada</th><th class="text-end waflow-actions-column">Ações</th></tr></thead><tbody><tr v-for="member in pageData.members" :key="member.id"><td><div class="font-medium text-mono">{{ member.name }}</div><div class="text-xs text-muted-foreground">{{ member.email }}</div></td><td>{{ partnerRoleLabel(member.role) }}</td><td>{{ statusLabel(member.status) }}</td><td>{{ member.joined_at_label || '-' }}</td><td class="waflow-actions-column"><form v-if="pageData.can_manage && member.role !== 'owner'" class="flex justify-end" :action="member.remove_url" method="post" @submit="confirmSubmit($event, `Remover ${member.name} da equipe do parceiro?`)"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-ghost waflow-action-destructive" type="submit">Remover</button></form></td></tr><tr v-if="!pageData.members?.length"><td colspan="5"><PanelEmptyState icon="ki-people" title="Nenhum membro" description="Adicione usuários já cadastrados na Waflow." /></td></tr></tbody></table></div></div>
                </div>
            </template>
        </PanelTabs>

        <PanelModal v-model="memberModalOpen" title="Adicionar membro" description="O email precisa pertencer a uma conta Waflow existente.">
            <form class="grid gap-4" :action="routeGroup('partner').membersStore" method="post"><input name="_token" type="hidden" :value="csrfToken"><div class="grid gap-1"><label class="kt-form-label" for="partner-member-email">Email</label><input id="partner-member-email" class="kt-input" name="email" type="email" autocomplete="email" required data-modal-autofocus></div><div class="grid gap-1"><label class="kt-form-label" for="partner-member-role">Papel</label><select id="partner-member-role" class="kt-input" name="role" required><option value="admin">Administrador</option><option value="sales">Comercial</option><option value="support">Suporte</option></select></div><div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="memberModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Adicionar membro</button></div></form>
        </PanelModal>
    </PanelShell>
</template>
