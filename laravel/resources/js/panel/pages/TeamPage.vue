<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTabs from '../PanelTabs.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, confirmSubmit, roleLabel, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
const members = computed(() => pageData.value.members || []);
const invitations = computed(() => pageData.value.invitations || []);
const memberTable = useCollectionTable(members, { searchKeys: ['name', 'email', 'role', 'status'], sortKey: 'name' });
const invitationTable = useCollectionTable(invitations, { searchKeys: ['email', 'role', 'status'], sortKey: 'email' });
const activeTab = ref('team-members');
const inviteModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.email || props.payload.flash?.fieldErrors?.role));
const tabs = computed(() => [
    { id: 'team-members', label: 'Membros', icon: 'ki-people', count: members.value.length },
    { id: 'team-invitations', label: 'Convites', icon: 'ki-paper-plane', count: invitations.value.length },
]);
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button v-if="permissions.manage_team" class="kt-btn kt-btn-primary" type="button" @click="inviteModalOpen = true"><i class="ki-filled ki-plus"></i>Convidar membro</button>
        </template>

        <PanelTabs v-model="activeTab" :tabs="tabs" label="Equipe">
            <template #team-members>
                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Membros</h3><p class="kt-card-description">Pessoas com acesso ativo a este workspace.</p></div></div>
                    <PanelTableToolbar v-model="memberTable.query.value" v-model:status="memberTable.status.value" :statuses="memberTable.statusOptions.value" :count="memberTable.rows.value.length" :total="memberTable.sourceRows.value.length" placeholder="Buscar membro ou email" @reset="memberTable.resetFilters" />
                    <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                        <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="memberTable.sortKey.value" :direction="memberTable.sortDirection.value" @sort="memberTable.toggleSort" /></th><th><PanelSortButton label="Email" field="email" :active-field="memberTable.sortKey.value" :direction="memberTable.sortDirection.value" @sort="memberTable.toggleSort" /></th><th><PanelSortButton label="Papel" field="role" :active-field="memberTable.sortKey.value" :direction="memberTable.sortDirection.value" @sort="memberTable.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="memberTable.sortKey.value" :direction="memberTable.sortDirection.value" @sort="memberTable.toggleSort" /></th><th>Entrada</th></tr></thead>
                        <tbody>
                            <tr v-for="member in memberTable.rows.value" :key="member.id"><td class="font-medium text-mono">{{ member.name }}</td><td>{{ member.email }}</td><td><span class="kt-badge kt-badge-light kt-badge-primary">{{ roleLabel(member.role) }}</span></td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(member.status)}`">{{ statusLabel(member.status) }}</span></td><td>{{ member.joined_at_label || '-' }}</td></tr>
                            <tr v-if="!memberTable.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-people" :title="memberTable.sourceRows.value.length ? 'Nenhum membro encontrado' : 'Nenhum membro ativo'" :description="memberTable.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Convide pessoas para colaborar neste workspace.'" :action-button="!memberTable.sourceRows.value.length && permissions.manage_team" action-label="Convidar membro" :resettable="Boolean(memberTable.sourceRows.value.length)" @action="inviteModalOpen = true" @reset="memberTable.resetFilters" /></td></tr>
                        </tbody>
                    </table></div></div>
                </div>
            </template>

            <template #team-invitations>
                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Convites</h3><p class="kt-card-description">Acompanhe convites pendentes, aceitos ou revogados.</p></div></div>
                    <PanelTableToolbar v-model="invitationTable.query.value" v-model:status="invitationTable.status.value" :statuses="invitationTable.statusOptions.value" :count="invitationTable.rows.value.length" :total="invitationTable.sourceRows.value.length" placeholder="Buscar convite ou email" @reset="invitationTable.resetFilters" />
                    <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                        <thead><tr><th><PanelSortButton label="Email" field="email" :active-field="invitationTable.sortKey.value" :direction="invitationTable.sortDirection.value" @sort="invitationTable.toggleSort" /></th><th><PanelSortButton label="Papel" field="role" :active-field="invitationTable.sortKey.value" :direction="invitationTable.sortDirection.value" @sort="invitationTable.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="invitationTable.sortKey.value" :direction="invitationTable.sortDirection.value" @sort="invitationTable.toggleSort" /></th><th>Expira em</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                        <tbody>
                            <tr v-for="invitation in invitationTable.rows.value" :key="invitation.uuid"><td>{{ invitation.email }}</td><td>{{ roleLabel(invitation.role) }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invitation.status)}`">{{ statusLabel(invitation.status) }}</span></td><td>{{ invitation.expires_at_label }}</td><td class="waflow-actions-column"><form v-if="permissions.manage_team && invitation.status === 'pending'" class="flex justify-end" :action="invitation.revoke_url" method="post" @submit="confirmSubmit($event, `Revogar o convite enviado para ${invitation.email}?`)"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-ghost waflow-action-destructive" type="submit">Revogar</button></form></td></tr>
                            <tr v-if="!invitationTable.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-paper-plane" :title="invitationTable.sourceRows.value.length ? 'Nenhum convite encontrado' : 'Nenhum convite enviado'" :description="invitationTable.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Os convites pendentes e concluídos aparecerão aqui.'" :action-button="!invitationTable.sourceRows.value.length && permissions.manage_team" action-label="Convidar membro" :resettable="Boolean(invitationTable.sourceRows.value.length)" @action="inviteModalOpen = true" @reset="invitationTable.resetFilters" /></td></tr>
                        </tbody>
                    </table></div></div>
                </div>
            </template>
        </PanelTabs>

        <PanelModal v-model="inviteModalOpen" title="Convidar membro" description="Envie um convite e defina o nível de acesso da pessoa.">
            <form v-if="permissions.manage_team" class="grid gap-4" :action="routeGroup('team').store" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="team-email">Email</label><input id="team-email" class="kt-input" name="email" autocomplete="email" placeholder="pessoa@empresa.com" type="email" required data-modal-autofocus></div>
                <div class="grid gap-1"><label class="kt-form-label" for="team-role">Papel</label><select id="team-role" class="kt-input" name="role" required><option value="admin">Administrador</option><option value="developer">Desenvolvedor</option><option value="support">Suporte</option><option value="member">Membro</option></select><p class="text-xs text-muted-foreground">Administradores podem gerenciar equipe, cobrança e configurações.</p></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="inviteModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Enviar convite</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
