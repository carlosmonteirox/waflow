<script setup>
import PanelShell from '../PanelShell.vue';
import { usePanelContext } from '../usePanelContext';

const endpoints = [
    ['GET', '/api/v1/health', 'Saúde pública da plataforma'],
    ['GET', '/api/v1/plans', 'Planos disponíveis'],
    ['GET', '/api/v1/workspace', 'Workspace autenticado'],
    ['PATCH', '/api/v1/workspace', 'Atualizar workspace'],
    ['GET|POST', '/api/v1/instances', 'Listar e criar instâncias'],
    ['POST', '/api/v1/instances/{uuid}/connect', 'Solicitar conexão'],
    ['GET|POST', '/api/v1/messages', 'Consultar e enfileirar mensagens'],
    ['GET|POST', '/api/v1/contacts', 'Base de contatos'],
    ['GET|POST', '/api/v1/portals', 'Sites vinculados ao workspace'],
    ['GET|POST', '/api/v1/flows', 'Fluxos de automação'],
    ['GET|POST', '/api/v1/team/invitations', 'Convites e equipe'],
    ['GET|POST', '/api/v1/webhooks', 'Callbacks de eventos'],
    ['GET|POST|DELETE', '/api/v1/api-keys', 'Gerenciar chaves'],
    ['GET', '/api/v1/billing/usage', 'Uso e limites do plano'],
    ['POST', '/api/v1/billing/checkouts', 'Checkout e autorização Pix Automático'],
];

const props = defineProps({ payload: { type: Object, required: true } });
const { pageData } = usePanelContext(props);
</script>

<template>
    <PanelShell :payload="payload">
        <section class="waflow-grid waflow-grid-3">
            <div class="kt-card waflow-span-2">
                <div class="kt-card-header"><div><h3 class="kt-card-title">Endpoints v1</h3><p class="kt-card-description">Contrato inicial para automação SaaS.</p></div></div>
                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                    <thead><tr><th>Método</th><th>Rota</th><th>Uso</th></tr></thead>
                    <tbody><tr v-for="[method, path, usageText] in endpoints" :key="`${method}-${path}`"><td><span class="kt-badge kt-badge-light kt-badge-primary">{{ method }}</span></td><td><code class="text-xs">{{ path }}</code></td><td>{{ usageText }}</td></tr></tbody>
                </table></div></div>
            </div>
            <div class="waflow-card-stack">
                <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Autenticação</h3></div><div class="kt-card-content p-5 grid gap-3"><div class="text-sm text-secondary-foreground">Bearer token</div><code class="waflow-code">Authorization: Bearer waflow_xxxxxxxxxxxxxxxxx</code><div class="text-sm text-secondary-foreground">Header alternativo</div><code class="waflow-code">X-Waflow-Key: waflow_xxxxxxxxxxxxxxxxx</code><a class="kt-btn kt-btn-primary justify-center" :href="pageData.openapi_url" download>Baixar OpenAPI 3.1</a></div></div>
                <div class="kt-card"><div class="kt-card-header"><h3 class="kt-card-title">Exemplo</h3></div><div class="kt-card-content p-5"><code class="waflow-code">curl -X POST {{ pageData.messages_endpoint }}
  -H "Authorization: Bearer waflow_xxx"
  -H "Content-Type: application/json"
  -d '{"to":"+5562999999999","body":"Ola pelo Waflow"}'</code><div class="text-sm text-secondary-foreground mt-4">Erros seguem o contrato <code>error.code</code>, <code>error.message</code>, <code>error.details</code> e <code>meta.request_id</code>.</div></div></div>
            </div>
        </section>
    </PanelShell>
</template>
