<script setup>
import PanelShell from '../PanelShell.vue';
import { badgeVariant } from '../panelFormatters';
import { usePanelContext } from '../usePanelContext';

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData, csrfToken, routeGroup } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><h3 class="kt-card-title">Fluxos</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Nome</th><th>Gatilho</th><th>Status</th><th>Última execução</th><th>Execuções</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                        <tr v-for="flow in pageData.flows" :key="flow.uuid">
                            <td class="font-medium text-mono">{{ flow.name }}</td><td>{{ flow.trigger_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(flow.status)}`">{{ flow.status }}</span></td><td>{{ flow.last_run_human || '-' }}</td><td>{{ flow.run_count || 0 }} / {{ flow.failure_count || 0 }} falhas</td>
                            <td><div class="flex justify-end gap-2"><form :action="flow.toggle_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ flow.status === 'active' ? 'Pausar' : 'Ativar' }}</button></form><form :action="flow.test_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Testar</button></form></div></td>
                        </tr>
                        <tr v-if="!pageData.flows?.length"><td colspan="6" class="text-center text-secondary-foreground py-10">Nenhum fluxo criado.</td></tr>
                    </tbody>
                </table></div></div>
            </div>

            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">Novo fluxo</h3></div>
                <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('flows').store" method="post">
                    <input name="_token" type="hidden" :value="csrfToken">
                    <div class="grid gap-1"><label class="kt-form-label" for="name">Nome</label><input id="name" class="kt-input" name="name" placeholder="Boas-vindas" required></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="trigger_type">Gatilho</label><select id="trigger_type" class="kt-input" name="trigger_type" required><option value="message_received">Mensagem recebida</option><option value="message_failed">Mensagem com falha</option><option value="instance_connected">Instância conectada</option></select></div>
                    <div class="grid gap-1"><label class="kt-form-label" for="flow_status">Status inicial</label><select id="flow_status" class="kt-input" name="status"><option value="draft">Rascunho</option><option value="active">Ativo</option></select></div>
                    <div class="border-t border-border pt-3 grid gap-3"><div class="text-sm font-medium text-mono">Condição opcional</div><input class="kt-input" name="condition_field" placeholder="trigger.body"><select class="kt-input" name="condition_operator"><option value="contains">Contém</option><option value="equals">Igual</option><option value="not_equals">Diferente</option><option value="starts_with">Começa com</option><option value="ends_with">Termina com</option><option value="exists">Existe</option></select><input class="kt-input" name="condition_value" placeholder="Valor esperado"></div>
                    <div class="border-t border-border pt-3 grid gap-3"><div class="text-sm font-medium text-mono">Resposta automática</div><select class="kt-input" name="instance_uuid"><option value="">Usar instância do evento</option><option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid">{{ instance.name }}</option></select><input class="kt-input" name="recipient" placeholder="Vazio usa o remetente do evento"><textarea class="kt-input min-h-24" name="message_text" placeholder="Olá! Recebemos sua mensagem."></textarea></div>
                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar fluxo</button>
                </form>
            </div>

            <div class="kt-card waflow-span-3">
                <div class="kt-card-header"><h3 class="kt-card-title">Execuções recentes</h3></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Fluxo</th><th>Gatilho</th><th>Status</th><th>Data</th><th>Erro</th></tr></thead>
                    <tbody><tr v-for="execution in pageData.executions || []" :key="execution.uuid"><td>{{ execution.flow_name }}</td><td>{{ execution.trigger_type }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(execution.status)}`">{{ execution.status }}</span></td><td>{{ execution.created_at_label }}</td><td class="max-w-[320px] truncate">{{ execution.error || '-' }}</td></tr><tr v-if="!pageData.executions?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhuma execução registrada.</td></tr></tbody>
                </table></div></div>
            </div>
        </section>
    </PanelShell>
</template>
