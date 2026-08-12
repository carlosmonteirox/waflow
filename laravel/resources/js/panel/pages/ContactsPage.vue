<script setup>
import PanelShell from '../PanelShell.vue';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><h3 class="kt-card-title">Contatos</h3><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.contacts?.total || 0 }} contatos</span></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Telefone</th><th>WA ID</th><th>Última atividade</th></tr></thead>
                    <tbody><tr v-for="contact in pageData.contacts?.items" :key="contact.uuid"><td class="font-medium text-mono">{{ contact.name || 'Sem nome' }}</td><td>{{ contact.phone_number }}</td><td>{{ contact.wa_id || '-' }}</td><td>{{ contact.last_seen_human || '-' }}</td></tr><tr v-if="!pageData.contacts?.items?.length"><td colspan="4" class="text-center text-secondary-foreground py-10">Nenhum contato salvo.</td></tr></tbody>
                </table></div></div>
                <div v-if="pageData.contacts?.links?.length" class="kt-card-footer"><div class="flex flex-wrap gap-2"><a v-for="link in pageData.contacts.links" :key="`${link.label}-${link.url}`" class="kt-btn kt-btn-sm" :class="link.active ? 'kt-btn-primary' : 'kt-btn-outline'" :href="link.url || '#'" :aria-disabled="!link.url">{{ link.label }}</a></div></div>
            </div>
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Adicionar contato</h3></div>
                <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('contacts').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="name">Nome</label><input id="name" class="kt-input" name="name" placeholder="Maria Souza"></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="phone_number">Telefone</label><input id="phone_number" class="kt-input" name="phone_number" placeholder="+55 62 99999-9999" required></div>
                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Salvar contato</button>
                </form>
            </div>
        </section>
    </PanelShell>
</template>
