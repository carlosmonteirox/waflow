<script setup>
import { computed } from 'vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, formatNumber, progressWidth } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, pages, usage } = usePanelContext(props);

const usageLabels = {
    messages: 'Mensagens',
    instances: 'Instâncias',
    webhooks: 'Webhooks',
    api_keys: 'Chaves API',
    portals: 'Portais',
};

const metricCards = computed(() => {
    const metrics = pageData.value.metrics || {};

    return [
        { label: 'Instâncias', icon: 'ki-whatsapp', badge: 'success', value: metrics.instances ?? 0, suffix: `${usage.value.instances?.used ?? 0}/${usage.value.instances?.limit ?? 0}`, description: 'Sessões criadas no workspace' },
        { label: 'Conectadas', icon: 'ki-wifi', badge: 'info', value: metrics.connected ?? 0, suffix: 'Agora', description: 'Prontas para envio e recebimento' },
        { label: 'Mensagens', icon: 'ki-messages', badge: 'primary', value: formatNumber(metrics.messages ?? 0), suffix: 'Mês atual', description: 'Eventos processados e enfileirados' },
        { label: 'Falhas', icon: 'ki-information-2', badge: 'destructive', value: metrics.failures ?? 0, suffix: 'Mês atual', description: 'Entregas com erro registradas' },
    ];
});
</script>

<template>
    <PanelShell :payload="payload">
        <section>
            <div class="waflow-grid waflow-grid-4 mb-5">
                <div v-for="card in metricCards" :key="card.label" class="kt-card">
                    <div class="kt-card-content p-5 flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="kt-badge kt-badge-light" :class="`kt-badge-${card.badge}`">
                                <i class="ki-filled" :class="card.icon"></i>
                                {{ card.label }}
                            </span>
                            <span class="text-xs text-secondary-foreground">{{ card.suffix }}</span>
                        </div>
                        <div class="text-3xl font-semibold text-mono">{{ card.value }}</div>
                        <div class="text-sm text-secondary-foreground">{{ card.description }}</div>
                    </div>
                </div>
            </div>

            <div class="waflow-grid waflow-grid-3 mb-5">
                <div class="kt-card waflow-span-2">
                    <div class="kt-card-header">
                        <div>
                            <h3 class="kt-card-title">Saúde da plataforma</h3>
                            <p class="kt-card-description">Serviços essenciais do Waflow</p>
                        </div>
                    </div>
                    <div class="kt-card-content p-5 grid gap-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent"><i class="ki-filled ki-whatsapp text-xl text-primary"></i></span>
                                <div><div class="text-sm font-medium text-mono">OpenWA Engine</div><div class="text-xs text-secondary-foreground">Execução das sessões WhatsApp</div></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="pageData.openWa?.latency_ms" class="text-xs text-secondary-foreground">{{ pageData.openWa.latency_ms }} ms</span>
                                <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.openWa?.ok ? 'success' : 'destructive'}`">{{ pageData.openWa?.label }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent"><i class="ki-filled ki-data text-xl text-primary"></i></span>
                                <div><div class="text-sm font-medium text-mono">PostgreSQL</div><div class="text-xs text-secondary-foreground">Dados SaaS e schema OpenWA</div></div>
                            </div>
                            <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.databaseOnline ? 'success' : 'destructive'}`">{{ pageData.databaseOnline ? 'Operacional' : 'Indisponível' }}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent"><i class="ki-filled ki-flash-circle text-xl text-primary"></i></span>
                                <div><div class="text-sm font-medium text-mono">Redis</div><div class="text-xs text-secondary-foreground">Filas, cache e sessões do painel</div></div>
                            </div>
                            <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.redisConfigured ? 'success' : 'warning'}`">{{ pageData.redisConfigured ? 'Configurado' : 'Local' }}</span>
                        </div>
                    </div>
                </div>

                <div class="kt-card">
                    <div class="kt-card-header"><div><h3 class="kt-card-title">Uso do plano</h3><p class="kt-card-description">Ciclo atual</p></div></div>
                    <div class="kt-card-content p-5 grid gap-4">
                        <div v-for="(item, key) in usage" :key="key" class="grid gap-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-secondary-foreground">{{ usageLabels[key] || key }}</span>
                                <span class="text-mono font-medium">{{ formatNumber(item.used) }}/{{ formatNumber(item.limit) }}</span>
                            </div>
                            <div class="waflow-progress" :aria-label="`${usageLabels[key] || key}: ${item.percent}%`"><span :style="{ width: progressWidth(item) }"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="waflow-grid waflow-grid-2">
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Instâncias recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.instances">Ver todas</a></div>
                    <div class="kt-card-table"><div class="waflow-table-wrap waflow-table-compact"><table class="kt-table">
                        <thead><tr><th>Nome</th><th>Status</th><th>Telefone</th></tr></thead>
                        <tbody>
                            <tr v-for="instance in pageData.recentInstances" :key="instance.uuid"><td class="font-medium text-mono">{{ instance.name }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ instance.status_label }}</span></td><td>{{ instance.phone_number || '-' }}</td></tr>
                            <tr v-if="!pageData.recentInstances?.length"><td colspan="3" class="text-center text-secondary-foreground py-8">Nenhuma instância criada.</td></tr>
                        </tbody>
                    </table></div></div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Eventos recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.messages">Abrir mensagens</a></div>
                    <div class="kt-card-table"><div class="waflow-table-wrap waflow-table-compact"><table class="kt-table">
                        <thead><tr><th>Destino</th><th>Status</th><th>Quando</th></tr></thead>
                        <tbody>
                            <tr v-for="message in pageData.recentMessages" :key="message.uuid"><td class="font-medium text-mono">{{ message.to || message.from || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ message.status }}</span></td><td>{{ message.created_human }}</td></tr>
                            <tr v-if="!pageData.recentMessages?.length"><td colspan="3" class="text-center text-secondary-foreground py-8">Nenhuma mensagem registrada.</td></tr>
                        </tbody>
                    </table></div></div>
                </div>
            </div>
        </section>
    </PanelShell>
</template>
