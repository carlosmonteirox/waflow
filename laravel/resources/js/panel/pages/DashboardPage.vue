<script setup>
import { computed } from 'vue';
import PanelEmptyState from '../PanelEmptyState.vue';
import PanelShell from '../PanelShell.vue';
import { badgeVariant, formatNumber, statusLabel } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, pages, usage } = usePanelContext(props);

const metricCards = computed(() => {
    const metrics = pageData.value.metrics || {};

    return [
        { label: 'Instâncias', icon: 'ki-whatsapp', badge: 'success', value: metrics.instances ?? 0, suffix: `${usage.value.instances?.used ?? 0}/${usage.value.instances?.limit ?? 0}`, description: 'Sessões criadas no workspace', url: pages.value.instances },
        { label: 'Conectadas', icon: 'ki-wifi', badge: 'info', value: metrics.connected ?? 0, suffix: 'Agora', description: 'Prontas para envio e recebimento', url: pages.value.instances },
        { label: 'Mensagens', icon: 'ki-messages', badge: 'primary', value: formatNumber(metrics.messages ?? 0), suffix: 'Mês atual', description: 'Eventos processados e enfileirados', url: pages.value.messages },
        { label: 'Falhas', icon: 'ki-information-2', badge: 'destructive', value: metrics.failures ?? 0, suffix: 'Mês atual', description: 'Entregas com erro registradas', url: `${pages.value.messages}?status=failed` },
    ];
});

</script>

<template>
    <PanelShell :payload="payload">
        <section>
            <div class="waflow-grid waflow-grid-4 mb-5">
                <a v-for="card in metricCards" :key="card.label" class="kt-card waflow-metric-card" :href="card.url" :aria-label="`${card.label}: ${card.value}. Abrir detalhes.`">
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
                </a>
            </div>

            <div class="waflow-grid waflow-grid-2">
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Instâncias recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.instances">Ver todas</a></div>
                    <div class="kt-card-table"><div class="waflow-table-wrap waflow-table-compact"><table class="kt-table">
                        <thead><tr><th>Nome</th><th>Status</th><th>Telefone</th></tr></thead>
                        <tbody>
                            <tr v-for="instance in pageData.recentInstances" :key="instance.uuid"><td class="font-medium text-mono">{{ instance.name }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ statusLabel(instance.status) }}</span></td><td>{{ instance.phone_number || '-' }}</td></tr>
                            <tr v-if="!pageData.recentInstances?.length"><td colspan="3"><PanelEmptyState icon="ki-whatsapp" title="Nenhuma instância criada" description="Crie a primeira instância para conectar o WhatsApp." :action-href="pages.instances" action-label="Criar instância" /></td></tr>
                        </tbody>
                    </table></div></div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-header"><h3 class="kt-card-title">Eventos recentes</h3><a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.messages">Abrir mensagens</a></div>
                    <div class="kt-card-table"><div class="waflow-table-wrap waflow-table-compact"><table class="kt-table">
                        <thead><tr><th>Destino</th><th>Status</th><th>Quando</th></tr></thead>
                        <tbody>
                            <tr v-for="message in pageData.recentMessages" :key="message.uuid"><td class="font-medium text-mono">{{ message.to || message.from || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ statusLabel(message.status) }}</span></td><td>{{ message.created_human }}</td></tr>
                            <tr v-if="!pageData.recentMessages?.length"><td colspan="3"><PanelEmptyState icon="ki-messages" title="Nenhuma mensagem registrada" description="As mensagens recentes aparecerão aqui." :action-href="pages.messages" action-label="Abrir mensagens" /></td></tr>
                        </tbody>
                    </table></div></div>
                </div>
            </div>
        </section>
    </PanelShell>
</template>
