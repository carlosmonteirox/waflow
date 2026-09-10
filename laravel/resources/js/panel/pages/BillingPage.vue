<script setup>
import { computed, ref } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelModal from '../PanelModal.vue';
import PanelShell from '../PanelShell.vue';
import PanelSortButton from '../PanelSortButton.vue';
import PanelTabs from '../PanelTabs.vue';
import PanelTableToolbar from '../PanelTableToolbar.vue';
import { badgeVariant, confirmSubmit, copyText, formatNumber, paymentMethodLabel, progressWidth, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';
import { useCollectionTable } from '../useCollectionTable';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup, plan, workspace, usage, user } = usePanelContext(props);

const usageLabels = {
    api_keys: 'Chaves API',
    instances: 'Instâncias',
    messages: 'Mensagens',
    portals: 'Sites/domínios',
    webhooks: 'Webhooks',
};

const latestPixCheckout = computed(() => (pageData.value.checkouts || []).find((checkout) => checkout.pix_copy_paste));
const checkouts = computed(() => pageData.value.checkouts || []);
const invoices = computed(() => pageData.value.invoices || []);
const checkoutTable = useCollectionTable(checkouts, { searchKeys: ['plan', 'payment_method', 'status', 'amount_label'], sortKey: 'created_at_label', sortDirection: 'desc' });
const invoiceTable = useCollectionTable(invoices, { searchKeys: ['number', 'status', 'amount_label'], sortKey: 'due_at_label', sortDirection: 'desc' });
const activeTab = ref('billing-summary');
const checkoutModalOpen = ref(Boolean(
    props.payload.flash?.fieldErrors?.plan_slug
    || props.payload.flash?.fieldErrors?.payment_method
    || props.payload.flash?.fieldErrors?.payer_name
    || props.payload.flash?.fieldErrors?.payer_tax_id,
));
const pixCopied = ref(false);
const tabs = computed(() => [
    { id: 'billing-summary', label: 'Plano e consumo', icon: 'ki-chart-line-up' },
    { id: 'billing-payments', label: 'Pagamentos', icon: 'ki-credit-cart', count: checkouts.value.length },
    { id: 'billing-invoices', label: 'Faturas', icon: 'ki-bill', count: invoices.value.length },
]);

async function copyPixCode() {
    if (!latestPixCheckout.value?.pix_copy_paste) return;

    await copyText(latestPixCheckout.value.pix_copy_paste);
    pixCopied.value = true;
    window.setTimeout(() => {
        pixCopied.value = false;
    }, 2000);
}

function usageTone(percent) {
    if (Number(percent) >= 95) return 'is-danger';
    if (Number(percent) >= 80) return 'is-warning';

    return '';
}
</script>

<template>
    <PanelShell :payload="payload">
        <template #page-actions>
            <button v-if="permissions.manage_billing" class="kt-btn kt-btn-primary" type="button" @click="checkoutModalOpen = true"><i class="ki-filled ki-switch"></i>Alterar plano</button>
        </template>

        <PanelTabs v-model="activeTab" :tabs="tabs" label="Plano e consumo">
            <template #billing-summary>
                <div class="waflow-grid waflow-grid-3">
                    <div class="kt-card">
                        <div class="kt-card-header"><h3 class="kt-card-title">Plano atual</h3></div>
                        <div class="kt-card-content p-5 grid gap-4">
                            <div><div class="text-2xl font-semibold text-mono">{{ plan?.name || 'Growth' }}</div><div class="text-sm text-secondary-foreground">{{ plan?.monthly_price_label || 'R$ 0,00' }}/mês</div></div>
                            <span class="kt-badge kt-badge-light w-fit" :class="`kt-badge-${badgeVariant(workspace.subscription_status || 'trialing')}`">{{ statusLabel(workspace.subscription_status || 'trialing') }}</span>
                            <div class="text-sm text-secondary-foreground">Ciclo até {{ workspace.current_period_ends_at_label || '-' }}</div>
                            <button v-if="permissions.manage_billing" class="kt-btn kt-btn-outline justify-center" type="button" @click="checkoutModalOpen = true">Ver planos disponíveis</button>
                        </div>
                    </div>
                    <div class="kt-card waflow-span-2">
                        <div class="kt-card-header"><h3 class="kt-card-title">Consumo</h3></div>
                        <div class="kt-card-content p-5 grid gap-5">
                            <div v-for="(item, key) in usage" :key="key" class="grid gap-2">
                                <div class="flex items-center justify-between text-sm"><span class="font-medium text-mono">{{ usageLabels[key] || key }}</span><span class="text-secondary-foreground">{{ formatNumber(item.used) }} de {{ formatNumber(item.limit) }}</span></div>
                                <div class="waflow-progress" :class="usageTone(item.percent)" role="progressbar" :aria-label="usageLabels[key] || key" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="item.percent ?? 0"><span :style="{ width: progressWidth(item) }"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="kt-card waflow-span-3">
                        <div class="kt-card-header"><h3 class="kt-card-title">Recursos do plano</h3></div>
                        <div class="kt-card-content p-5 grid gap-3 md:grid-cols-2"><div v-for="feature in plan?.features || []" :key="feature" class="flex items-center gap-2 text-sm"><i class="ki-filled ki-check-circle text-success"></i><span>{{ feature }}</span></div></div>
                    </div>
                </div>
            </template>

            <template #billing-payments>
                <div class="grid gap-5">
                    <div v-if="latestPixCheckout" class="kt-card">
                        <div class="kt-card-header"><h3 class="kt-card-title">Autorizar Pix Automático</h3></div>
                        <div class="kt-card-content p-5 grid gap-4">
                            <p class="text-sm text-secondary-foreground">Copie o código abaixo no aplicativo do seu banco para autorizar as próximas cobranças mensais.</p>
                            <textarea class="kt-input min-h-28 font-mono text-xs" aria-label="Código Pix copia e cola" readonly :value="latestPixCheckout.pix_copy_paste"></textarea>
                            <div class="flex flex-wrap gap-2"><button class="kt-btn kt-btn-primary" type="button" @click="copyPixCode"><i class="ki-filled" :class="pixCopied ? 'ki-check' : 'ki-copy'"></i>{{ pixCopied ? 'Código copiado' : 'Copiar código Pix' }}</button><a v-if="latestPixCheckout.checkout_url" class="kt-btn kt-btn-outline" :href="latestPixCheckout.checkout_url" target="_blank" rel="noopener">Abrir autorização <i class="ki-filled ki-arrow-up-right"></i></a></div>
                        </div>
                    </div>

                    <div class="kt-card">
                        <div class="kt-card-header"><div><h3 class="kt-card-title">Checkouts recentes</h3><p class="kt-card-description">Histórico das tentativas de contratação e troca de plano.</p></div><button v-if="permissions.manage_billing" class="kt-btn kt-btn-sm kt-btn-outline" type="button" @click="checkoutModalOpen = true">Novo checkout</button></div>
                        <PanelTableToolbar v-model="checkoutTable.query.value" v-model:status="checkoutTable.status.value" :statuses="checkoutTable.statusOptions.value" :count="checkoutTable.rows.value.length" :total="checkoutTable.sourceRows.value.length" placeholder="Buscar checkout, plano ou forma de pagamento" @reset="checkoutTable.resetFilters" />
                        <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                            <thead><tr><th><PanelSortButton label="Plano" field="plan" :active-field="checkoutTable.sortKey.value" :direction="checkoutTable.sortDirection.value" @sort="checkoutTable.toggleSort" /></th><th>Forma</th><th>Valor</th><th><PanelSortButton label="Status" field="status" :active-field="checkoutTable.sortKey.value" :direction="checkoutTable.sortDirection.value" @sort="checkoutTable.toggleSort" /></th><th><PanelSortButton label="Data" field="created_at_label" :active-field="checkoutTable.sortKey.value" :direction="checkoutTable.sortDirection.value" @sort="checkoutTable.toggleSort" /></th></tr></thead>
                            <tbody><tr v-for="checkout in checkoutTable.rows.value" :key="checkout.uuid"><td>{{ checkout.plan }}</td><td>{{ paymentMethodLabel(checkout.payment_method) }}</td><td>{{ checkout.amount_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(checkout.status)}`">{{ statusLabel(checkout.status) }}</span></td><td>{{ checkout.created_at_label }}</td></tr><tr v-if="!checkoutTable.rows.value.length"><td colspan="5"><PanelEmptyState icon="ki-credit-cart" :title="checkoutTable.sourceRows.value.length ? 'Nenhum checkout encontrado' : 'Nenhum checkout iniciado'" :description="checkoutTable.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'Os checkouts recentes aparecerão aqui.'" :action-button="!checkoutTable.sourceRows.value.length && permissions.manage_billing" action-label="Preparar checkout" :resettable="Boolean(checkoutTable.sourceRows.value.length)" @action="checkoutModalOpen = true" @reset="checkoutTable.resetFilters" /></td></tr></tbody>
                        </table></div></div>
                    </div>
                </div>
            </template>

            <template #billing-invoices>
                <div class="kt-card">
                    <div class="kt-card-header">
                        <div><h3 class="kt-card-title">Faturas e renovação</h3><p class="kt-card-description">Consulte cobranças e gerencie a renovação do plano.</p></div>
                        <form v-if="permissions.manage_billing && workspace.cancel_at_period_end" :action="routeGroup('billing').resume" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Reativar renovação</button></form>
                        <form v-else-if="permissions.manage_billing" :action="routeGroup('billing').cancel" method="post" @submit="confirmSubmit($event, 'Cancelar a renovação ao fim do ciclo?')"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-ghost waflow-action-destructive" type="submit">Cancelar renovação</button></form>
                    </div>
                    <PanelTableToolbar v-model="invoiceTable.query.value" v-model:status="invoiceTable.status.value" :statuses="invoiceTable.statusOptions.value" :count="invoiceTable.rows.value.length" :total="invoiceTable.sourceRows.value.length" placeholder="Buscar fatura, valor ou status" @reset="invoiceTable.resetFilters" />
                    <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                        <thead><tr><th><PanelSortButton label="Número" field="number" :active-field="invoiceTable.sortKey.value" :direction="invoiceTable.sortDirection.value" @sort="invoiceTable.toggleSort" /></th><th>Valor</th><th><PanelSortButton label="Vencimento" field="due_at_label" :active-field="invoiceTable.sortKey.value" :direction="invoiceTable.sortDirection.value" @sort="invoiceTable.toggleSort" /></th><th><PanelSortButton label="Status" field="status" :active-field="invoiceTable.sortKey.value" :direction="invoiceTable.sortDirection.value" @sort="invoiceTable.toggleSort" /></th></tr></thead>
                        <tbody><tr v-for="invoice in invoiceTable.rows.value" :key="invoice.uuid"><td>{{ invoice.number }}</td><td>{{ invoice.amount_label }}</td><td>{{ invoice.due_at_label || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invoice.status)}`">{{ statusLabel(invoice.status) }}</span></td></tr><tr v-if="!invoiceTable.rows.value.length"><td colspan="4"><PanelEmptyState icon="ki-bill" :title="invoiceTable.sourceRows.value.length ? 'Nenhuma fatura encontrada' : 'Nenhuma fatura disponível'" :description="invoiceTable.sourceRows.value.length ? 'Tente remover os filtros ou buscar por outro termo.' : 'As faturas aparecerão aqui após a ativação do provedor.'" :resettable="Boolean(invoiceTable.sourceRows.value.length)" @reset="invoiceTable.resetFilters" /></td></tr></tbody>
                    </table></div></div>
                </div>
            </template>
        </PanelTabs>

        <PanelModal v-model="checkoutModalOpen" title="Alterar plano" description="Escolha o plano e a forma de pagamento para preparar o checkout.">
            <form v-if="permissions.manage_billing" class="grid gap-4" :action="routeGroup('billing').checkout" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <div class="grid gap-1"><label class="kt-form-label" for="checkout-plan">Plano</label><select id="checkout-plan" class="kt-input" name="plan_slug" required data-modal-autofocus><option v-for="availablePlan in pageData.plans || []" :key="availablePlan.slug" :value="availablePlan.slug">{{ availablePlan.name }} — {{ availablePlan.monthly_price_label }}/mês</option></select></div>
                <div class="grid gap-1"><label class="kt-form-label" for="checkout-payment">Pagamento</label><select id="checkout-payment" class="kt-input" name="payment_method" required><option v-for="method in pageData.payment_methods || []" :key="method" :value="method">{{ paymentMethodLabel(method) }}</option></select></div>
                <template v-if="pageData.provider === 'inter'"><div class="grid gap-1"><label class="kt-form-label" for="payer-name">Nome do pagador</label><input id="payer-name" class="kt-input" name="payer_name" autocomplete="name" :value="user.name" maxlength="120" required></div><div class="grid gap-1"><label class="kt-form-label" for="payer-tax-id">CPF ou CNPJ</label><input id="payer-tax-id" class="kt-input" name="payer_tax_id" inputmode="numeric" autocomplete="off" maxlength="18" placeholder="Somente números" required></div></template>
                <div class="text-xs text-secondary-foreground">Integração: {{ pageData.provider === 'inter' ? 'Banco Inter · Pix Automático' : (pageData.provider_status === 'configured' ? 'provedor configurado' : 'provedor ainda será escolhido') }}</div>
                <div class="waflow-modal-actions"><button class="kt-btn kt-btn-outline" type="button" @click="checkoutModalOpen = false">Cancelar</button><button class="kt-btn kt-btn-primary" type="submit">Preparar checkout</button></div>
            </form>
        </PanelModal>
    </PanelShell>
</template>
