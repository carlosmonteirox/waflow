<script setup>
import PanelShell from '../PanelShell.vue';
import { badgeVariant } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><h3 class="kt-card-title">Membros</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Email</th><th>Papel</th><th>Status</th><th>Entrada</th></tr></thead>
                    <tbody>
                        <tr v-for="member in pageData.members" :key="member.id"><td class="font-medium text-mono">{{ member.name }}</td><td>{{ member.email }}</td><td><span class="kt-badge kt-badge-light kt-badge-primary">{{ member.role }}</span></td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(member.status)}`">{{ member.status }}</span></td><td>{{ member.joined_at_label || '-' }}</td></tr>
                        <tr v-if="!pageData.members?.length"><td colspan="5" class="text-center text-secondary-foreground py-10">Nenhum membro ativo.</td></tr>
                    </tbody>
                </table></div></div>
            </div>

            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Convidar membro</h3></div>
                <form v-if="permissions.manage_team" class="kt-card-content p-5 grid gap-4" :action="routeGroup('team').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="team-email">Email</label><input id="team-email" class="kt-input" name="email" placeholder="pessoa@empresa.com" type="email" required></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="team-role">Papel</label><select id="team-role" class="kt-input" name="role" required><option value="admin">Admin</option><option value="developer">Developer</option><option value="support">Suporte</option><option value="member">Membro</option></select></div>
                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Enviar convite</button>
                </form>
                <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode convidar membros.</div>
            </div>

            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Convites</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Email</th><th>Papel</th><th>Status</th><th>Expira em</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="invitation in pageData.invitations || []" :key="invitation.uuid"><td>{{ invitation.email }}</td><td>{{ invitation.role }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invitation.status)}`">{{ invitation.status }}</span></td><td>{{ invitation.expires_at_label }}</td><td><form v-if="permissions.manage_team && invitation.status === 'pending'" class="flex justify-end" :action="invitation.revoke_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Revogar</button></form></td></tr>
                        <tr v-if="!pageData.invitations?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum convite enviado.</td></tr>
                    </tbody>
                </table></div></div>
            </div>
        </section>
    </PanelShell>
</template>
