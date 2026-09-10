<script setup>
import { ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import { formatNumber } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
const planModalOpen = ref(Boolean(Object.keys(props.payload.flash?.fieldErrors || {}).length));
const editingPlan = ref(null);

function editPlan(plan) {
    editingPlan.value = plan;
    planModalOpen.value = true;
}

function createPlan() {
    editingPlan.value = null;
    planModalOpen.value = true;
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions><button class="kt-btn kt-btn-primary" type="button" @click="createPlan"><i class="ki-filled ki-plus"></i>Novo plano</button></template>
        <div class="kt-card">
            <div class="kt-card-header"><div><h3 class="kt-card-title">Catálogo de planos</h3><p class="kt-card-description">Preços e limites aplicados às assinaturas.</p></div><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.plans?.length || 0 }} planos</span></div>
            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                <thead><tr><th>Plano</th><th>Preço mensal</th><th>Instâncias</th><th>Mensagens</th><th>Webhooks</th><th>Chaves</th><th>Sites/domínios</th><th>Assinaturas</th><th class="text-end waflow-actions-column">Ações</th></tr></thead>
                <tbody><tr v-for="plan in pageData.plans" :key="plan.id"><td><div class="font-medium text-mono">{{ plan.name }}</div><code class="text-xs">{{ plan.slug }}</code></td><td>{{ plan.monthly_price_label }}</td><td>{{ formatNumber(plan.instance_limit) }}</td><td>{{ formatNumber(plan.message_limit) }}</td><td>{{ formatNumber(plan.webhook_limit) }}</td><td>{{ formatNumber(plan.api_key_limit) }}</td><td>{{ formatNumber(plan.portal_limit) }}</td><td>{{ plan.subscriptions_count }}</td><td class="waflow-actions-column"><button class="kt-btn kt-btn-sm kt-btn-outline" type="button" @click="editPlan(plan)"><i class="ki-filled ki-pencil"></i>Editar</button></td></tr><tr v-if="!pageData.plans?.length"><td colspan="9"><PanelEmptyState icon="ki-price-tag" title="Nenhum plano cadastrado" action-label="Criar plano" action-button @action="createPlan" /></td></tr></tbody>
            </table></div></div>
        </div>

        <PanelModal v-model="planModalOpen" :title="editingPlan ? 'Editar plano' : 'Novo plano'" description="Valores monetários devem ser informados em centavos." size="lg">
            <form class="grid gap-4" :action="editingPlan?.update_url || routeGroup('admin').plansStore" method="post">
                <input name="_token" type="hidden" :value="csrfToken"><input v-if="editingPlan" name="_method" type="hidden" value="patch">
                <div class="grid gap-4 md:grid-cols-2"><div class="grid gap-1"><label class="kt-form-label" for="plan-name">Nome</label><input id="plan-name" class="kt-input" name="name" :value="editingPlan?.name" required data-modal-autofocus></div><div class="grid gap-1"><label class="kt-form-label" for="plan-slug">Slug</label><input id="plan-slug" class="kt-input" name="slug" :value="editingPlan?.slug" placeholder="growth" required></div></div>
                <div class="grid gap-1"><label class="kt-form-label" for="plan-price">Preço mensal em centavos</label><input id="plan-price" class="kt-input" name="monthly_price_cents" type="number" min="0" :value="editingPlan?.monthly_price_cents ?? 0" required></div>
                <div class="grid gap-4 md:grid-cols-2"><div class="grid gap-1"><label class="kt-form-label" for="plan-instances">Limite de instâncias</label><input id="plan-instances" class="kt-input" name="instance_limit" type="number" min="1" :value="editingPlan?.instance_limit ?? 1" required></div><div class="grid gap-1"><label class="kt-form-label" for="plan-messages">Limite de mensagens</label><input id="plan-messages" class="kt-input" name="message_limit" type="number" min="1" :value="editingPlan?.message_limit ?? 1000" required></div><div class="grid gap-1"><label class="kt-form-label" for="plan-webhooks">Limite de webhooks</label><input id="plan-webhooks" class="kt-input" name="webhook_limit" type="number" min="0" :value="editingPlan?.webhook_limit ?? 2" required></div><div class="grid gap-1"><label class="kt-form-label" for="plan-keys">Limite de chaves</label><input id="plan-keys" class="kt-input" name="api_key_limit" type="number" min="0" :value="editingPlan?.api_key_limit ?? 2" required></div><div class="grid gap-1"><label class="kt-form-label" for="plan-sites">Limite de sites/domínios</label><input id="plan-sites" class="kt-input" name="portal_limit" type="number" min="0" :value="editingPlan?.portal_limit ?? 1" required></div></div>
                <div class="grid gap-1"><label class="kt-form-label" for="plan-features">Recursos</label><textarea id="plan-features" class="kt-input min-h-28" name="features_text" placeholder="Um recurso por linha" :value="editingPlan?.features_text"></textarea></div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="planModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">{{ editingPlan ? 'Salvar plano' : 'Criar plano' }}</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
