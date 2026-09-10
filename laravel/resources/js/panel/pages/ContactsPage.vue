<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelPagination from '../PanelPagination.vue';
import PanelServerTableToolbar from '../PanelServerTableToolbar.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, pages, routeGroup } = usePanelContext(props);
const filters = computed(() => pageData.value.filters || {});
const createModalOpen = ref(Boolean(props.payload.flash?.fieldErrors?.phone_number || props.payload.flash?.fieldErrors?.name));

function sortUrl(field) {
    const url = new URL(window.location.href);
    const nextDirection = filters.value.sort === field && filters.value.direction === 'asc' ? 'desc' : 'asc';
    url.searchParams.set('sort', field);
    url.searchParams.set('direction', nextDirection);
    url.searchParams.delete('page');

    return url.toString();
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button class="kt-btn kt-btn-primary" type="button" @click="createModalOpen = true"><i class="ki-filled ki-user-tick"></i>Adicionar contato</button>
        </template>
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Contatos</h3><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.contacts?.total || 0 }} contatos</span></div>
                <PanelServerTableToolbar :action="pages.contacts" :query="filters.search" placeholder="Buscar por nome, telefone ou WA ID" />
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th><PanelSortButton label="Nome" field="name" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('name')" /></th><th><PanelSortButton label="Telefone" field="phone_number" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('phone_number')" /></th><th>WA ID</th><th><PanelSortButton label="Última atividade" field="last_seen_at" :active-field="filters.sort" :direction="filters.direction" :href="sortUrl('last_seen_at')" /></th></tr></thead>
                    <tbody>
                        <tr v-for="contact in pageData.contacts?.items" :key="contact.uuid"><td class="font-medium text-mono">{{ contact.name || 'Sem nome' }}</td><td>{{ contact.phone_number }}</td><td>{{ contact.wa_id || '-' }}</td><td>{{ contact.last_seen_human || '-' }}</td></tr>
                        <tr v-if="!pageData.contacts?.items?.length"><td colspan="4"><PanelEmptyState icon="ki-profile-circle" :title="filters.search ? 'Nenhum contato encontrado' : 'Nenhum contato salvo'" :description="filters.search ? 'Tente buscar por outro nome ou telefone.' : 'Cadastre o primeiro contato para começar sua base.'" :action-href="filters.search ? pages.contacts : ''" :action-button="!filters.search" :action-label="filters.search ? 'Limpar busca' : 'Adicionar contato'" @action="createModalOpen = true" /></td></tr>
                    </tbody>
                </table></div></div>
                <div class="kt-card-footer"><PanelPagination v-if="pageData.contacts?.total" :pagination="pageData.contacts" label="contatos" /></div>
            </div>
        </section>

        <PanelModal v-model="createModalOpen" title="Adicionar contato" description="Inclua uma pessoa na base deste workspace.">
                <form class="grid gap-4" :action="routeGroup('contacts').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="contact-name">Nome</label><input id="contact-name" class="kt-input" name="name" autocomplete="name" placeholder="Maria Souza" data-modal-autofocus></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="contact-phone">Telefone</label><input id="contact-phone" class="kt-input" name="phone_number" type="tel" inputmode="tel" autocomplete="tel" placeholder="+55 62 99999-9999" required></div>
                    <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="createModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Salvar contato</button></div>
                </form>
        </PanelModal>
    </PanelShell>
</template>
