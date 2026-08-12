<script setup>
import { computed } from 'vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, confirmSubmit, copyText, formatNumber, paymentMethodLabel, progressWidth } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, permissions, routeGroup, plan, workspace, usage, user } = usePanelContext(props);

const usageLabels = {
    instances: 'Instâncias',
    flows: 'Automações',
    messages: 'Mensagens',
    contacts: 'Contatos',
    portals: 'Portais',
    members: 'Membros',
};

const latestPixCheckout = computed(() => (pageData.value.checkouts || []).find((checkout) => checkout.pix_copy_paste));
</script>

<template>
    <PanelShell :payload="payload">
        <section>
            <div class="waflow-grid waflow-grid-3">
                <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Plano atual</h3></div><div class="kt-card-content p-5 grid gap-4"><div><div class="text-2xl font-semibold text-mono">{{ plan?.name || 'Growth' }}</div><div class="text-sm text-secondary-foreground">{{ plan?.monthly_price_label || 'R$ 0,00' }}/mês</div></div><span class="kt-badge kt-badge-light w-fit" :class="`kt-badge-${badgeVariant(workspace.subscription_status || 'trialing')}`">{{ workspace.subscription_status || 'trialing' }}</span><div class="text-sm text-secondary-foreground">Ciclo até {{ workspace.current_period_ends_at_label || '-' }}</div></div></div>
                <div class="kt-card waflow-span-2"><div class="kt-card-header"><h3 class="kt-card-title">Consumo</h3></div><div class="kt-card-content p-5 grid gap-5"><div v-for="(item, key) in usage" :key="key" class="grid gap-2"><div class="flex items-center justify-between text-sm"><span class="font-medium text-mono">{{ usageLabels[key] || key }}</span><span class="text-secondary-foreground">{{ formatNumber(item.used) }} de {{ formatNumber(item.limit) }}</span></div><div class="waflow-progress"><span :style="{ width: progressWidth(item) }"></span></div></div></div></div>
            </div>

            <div class="kt-card mt-5"><div class="kt-card-header"><h3 class="kt-card-title">Recursos do plano</h3></div><div class="kt-card-content p-5 grid gap-3 md:grid-cols-2"><div v-for="feature in plan?.features || []" :key="feature" class="flex items-center gap-2 text-sm"><i class="ki-filled ki-check-circle text-success"></i><span>{{ feature }}</span></div></div></div>

            <div class="waflow-grid waflow-grid-3 mt-5">
                <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Novo checkout</h3></div><form v-if="permissions.manage_billing" class="kt-card-content p-5 grid gap-4" :action="routeGroup('billing').checkout" method="post"><input name="_token" type="hidden" :value="csrfToken"><div class="grid gap-1"><label class="kt-form-label">Plano</label><select class="kt-input" name="plan_slug" required><option v-for="availablePlan in pageData.plans || []" :key="availablePlan.slug" :value="availablePlan.slug">{{ availablePlan.name }} — {{ availablePlan.monthly_price_label }}/mês</option></select></div><div class="grid gap-1"><label class="kt-form-label">Pagamento</label><select class="kt-input" name="payment_method" required><option v-for="method in pageData.payment_methods || []" :key="method" :value="method">{{ paymentMethodLabel(method) }}</option></select></div><template v-if="pageData.provider === 'inter'"><div class="grid gap-1"><label class="kt-form-label">Nome do pagador</label><input class="kt-input" name="payer_name" :value="user.name" maxlength="120" required></div><div class="grid gap-1"><label class="kt-form-label">CPF ou CNPJ</label><input class="kt-input" name="payer_tax_id" inputmode="numeric" maxlength="18" placeholder="Somente números" required></div></template><div class="text-xs text-secondary-foreground">Integração: {{ pageData.provider === 'inter' ? 'Banco Inter · Pix Automático' : (pageData.provider_status === 'configured' ? 'provedor configurado' : 'provedor ainda será escolhido') }}</div><button class="kt-btn kt-btn-primary justify-center" type="submit">Preparar checkout</button></form><div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode alterar o plano.</div></div>
                <div class="kt-card waflow-span-2"><div class="kt-card-header"><h3 class="kt-card-title">Checkouts recentes</h3></div><div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Plano</th><th>Forma</th><th>Valor</th><th>Status</th><th>Data</th></tr></thead><tbody><tr v-for="checkout in pageData.checkouts || []" :key="checkout.uuid"><td>{{ checkout.plan }}</td><td>{{ checkout.payment_method }}</td><td>{{ checkout.amount_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(checkout.status)}`">{{ checkout.status }}</span></td><td>{{ checkout.created_at_label }}</td></tr><tr v-if="!pageData.checkouts?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum checkout iniciado.</td></tr></tbody></table></div></div></div>
            </div>

            <div v-if="latestPixCheckout" class="kt-card mt-5"><div class="kt-card-header"><h3 class="kt-card-title">Autorizar Pix Automático</h3></div><div class="kt-card-content p-5 grid gap-4"><p class="text-sm text-secondary-foreground">Copie o código abaixo no aplicativo do seu banco para autorizar as próximas cobranças mensais.</p><textarea class="kt-input min-h-28 font-mono text-xs" readonly :value="latestPixCheckout.pix_copy_paste"></textarea><div class="flex flex-wrap gap-2"><button class="kt-btn kt-btn-primary" type="button" @click="copyText(latestPixCheckout.pix_copy_paste)">Copiar código Pix</button><a v-if="latestPixCheckout.checkout_url" class="kt-btn kt-btn-outline" :href="latestPixCheckout.checkout_url" target="_blank" rel="noopener">Abrir autorização</a></div></div></div>

            <div class="kt-card mt-5"><div class="kt-card-header"><h3 class="kt-card-title">Faturas e renovação</h3><form v-if="permissions.manage_billing && workspace.cancel_at_period_end" :action="routeGroup('billing').resume" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Reativar renovação</button></form><form v-else-if="permissions.manage_billing" :action="routeGroup('billing').cancel" method="post" @submit="confirmSubmit($event, 'Cancelar a renovação ao fim do ciclo?')"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Cancelar renovação</button></form></div><div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table"><thead><tr><th>Número</th><th>Valor</th><th>Vencimento</th><th>Status</th></tr></thead><tbody><tr v-for="invoice in pageData.invoices || []" :key="invoice.uuid"><td>{{ invoice.number }}</td><td>{{ invoice.amount_label }}</td><td>{{ invoice.due_at_label || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invoice.status)}`">{{ invoice.status }}</span></td></tr><tr v-if="!pageData.invoices?.length"><td colspan="4" class="text-center text-secondary-foreground py-8">As faturas aparecerão aqui após a ativação do provedor.</td></tr></tbody></table></div></div></div>
        </section>
    </PanelShell>
</template>
