<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
});

const sidebarOpen = ref(false);

const payload = computed(() => props.payload || {});
const page = computed(() => props.payload.page || 'dashboard');
const pageData = computed(() => props.payload.data || {});
const routes = computed(() => props.payload.routes || {});
const pages = computed(() => routes.value.pages || {});
const csrfToken = computed(() => props.payload.csrfToken || '');
const flash = computed(() => props.payload.flash || {});
const errors = computed(() => flash.value.errors || []);
const workspace = computed(() => props.payload.workspace || {});
const workspaces = computed(() => props.payload.workspaces || []);
const permissions = computed(() => workspace.value.permissions || {});
const plan = computed(() => props.payload.plan || { name: 'Growth', features: [] });
const user = computed(() => props.payload.user || {});
const usage = computed(() => props.payload.usage || {});
const navSections = computed(() => props.payload.navSections || []);
const qrInstance = computed(() => pageData.value.instances?.find((instance) => instance.qr_code));

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
    ['POST', '/api/v1/billing/checkouts', 'Checkout por cartão ou Pix'],
];

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
        {
            label: 'Instâncias',
            icon: 'ki-whatsapp',
            badge: 'success',
            value: metrics.instances ?? 0,
            suffix: `${usage.value.instances?.used ?? 0}/${usage.value.instances?.limit ?? 0}`,
            description: 'Sessões criadas no workspace',
        },
        {
            label: 'Conectadas',
            icon: 'ki-wifi',
            badge: 'info',
            value: metrics.connected ?? 0,
            suffix: 'Agora',
            description: 'Prontas para envio e recebimento',
        },
        {
            label: 'Mensagens',
            icon: 'ki-messages',
            badge: 'primary',
            value: formatNumber(metrics.messages ?? 0),
            suffix: 'Mês atual',
            description: 'Eventos processados e enfileirados',
        },
        {
            label: 'Falhas',
            icon: 'ki-information-2',
            badge: 'destructive',
            value: metrics.failures ?? 0,
            suffix: 'Mês atual',
            description: 'Entregas com erro registradas',
        },
    ];
});

function routeGroup(name) {
    return routes.value[name] || {};
}

function initials(value, fallback = 'WF') {
    const source = `${value || fallback}`.trim();

    return source.slice(0, 2).toUpperCase();
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('pt-BR');
}

function badgeVariant(status, fallback = 'secondary') {
    const variants = {
        active: 'success',
        connected: 'success',
        delivered: 'success',
        sent: 'success',
        trialing: 'success',
        online: 'success',
        qr_pending: 'warning',
        pending: 'warning',
        provider_pending: 'warning',
        queued: 'warning',
        draft: 'secondary',
        paused: 'secondary',
        revoked: 'secondary',
        accepted: 'success',
        completed: 'success',
        expired: 'destructive',
        inactive: 'secondary',
        disconnected: 'secondary',
        failed: 'destructive',
        error: 'destructive',
    };

    return variants[status] || fallback;
}

function confirmSubmit(event, message) {
    if (!window.confirm(message)) {
        event.preventDefault();
    }
}

function progressWidth(item) {
    return `${item?.percent ?? 0}%`;
}
</script>

<template>
    <div v-if="sidebarOpen" class="waflow-sidebar-overlay lg:hidden" @click="sidebarOpen = false"></div>

    <div class="flex grow">
        <aside
            class="kt-sidebar bg-background border-e border-e-border fixed top-0 bottom-0 z-20 flex flex-col items-stretch shrink-0 waflow-sidebar-mobile lg:translate-x-0"
            :class="{ 'is-open': sidebarOpen }"
            id="sidebar"
        >
            <div class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0" id="sidebar_header">
                <a class="flex items-center gap-2.5" :href="pages.dashboard">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-primary text-primary-foreground">
                        <i class="ki-filled ki-whatsapp text-xl"></i>
                    </span>
                    <span class="default-logo text-lg font-semibold text-mono">Waflow</span>
                    <span class="small-logo text-lg font-semibold text-mono">W</span>
                </a>
            </div>

            <div class="flex lg:hidden items-center justify-between px-4 py-4 border-b border-border/40">
                <a class="flex items-center gap-2.5" :href="pages.dashboard">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-primary text-primary-foreground">
                        <i class="ki-filled ki-whatsapp text-xl"></i>
                    </span>
                    <span class="text-lg font-semibold text-mono">Waflow</span>
                </a>
                <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Fechar menu" @click="sidebarOpen = false">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>

            <div class="px-3 lg:px-5 pt-3">
                <div class="rounded-lg border border-border/40 bg-accent/10 px-3 py-3">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center justify-center size-9 rounded-full bg-primary text-primary-foreground text-xs font-semibold">
                            {{ initials(workspace.name) }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-mono truncate">{{ workspace.name }}</div>
                            <div class="text-xs text-muted-foreground truncate">Plano {{ plan?.name || 'Growth' }}</div>
                            <div class="text-xs text-muted-foreground truncate">Perfil {{ workspace.member_role || 'member' }}</div>
                        </div>
                    </div>
                    <details v-if="workspaces.length > 1" class="mt-3 border-t border-border/40 pt-2">
                        <summary class="cursor-pointer text-xs font-medium text-primary">Trocar workspace</summary>
                        <div class="grid gap-1 mt-2">
                            <form v-for="available in workspaces.filter((item) => !item.active)" :key="available.uuid" :action="available.select_url" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <button class="kt-btn kt-btn-sm kt-btn-ghost w-full justify-start" type="submit">{{ available.name }} · {{ available.role }}</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <div class="kt-sidebar-content flex grow shrink-0 py-5 pe-2" id="sidebar_content">
                <div class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3" id="sidebar_scrollable">
                    <nav class="kt-menu flex flex-col grow gap-1" id="sidebar_menu">
                        <template v-for="section in navSections" :key="section.label">
                            <div class="kt-menu-item pt-2.25 pb-px">
                                <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">{{ section.label }}</span>
                            </div>

                            <div v-for="item in section.items" :key="item.route" class="kt-menu-item" :class="{ active: item.active }">
                                <a class="kt-menu-link border border-transparent items-center grow hover:bg-accent/60 hover:rounded-lg gap-[10px] ps-[10px] pe-[10px] py-[8px]" :class="{ 'bg-accent/60 rounded-lg': item.active }" :href="item.url" tabindex="0">
                                    <span class="kt-menu-icon items-start text-muted-foreground w-[20px]">
                                        <i class="ki-filled text-lg" :class="item.icon"></i>
                                    </span>
                                    <span class="kt-menu-title text-sm kt-menu-link-hover:!text-primary" :class="item.active ? 'font-semibold text-primary' : 'font-medium text-foreground'">{{ item.label }}</span>
                                </a>
                            </div>
                        </template>
                    </nav>
                </div>
            </div>
        </aside>

        <div class="kt-wrapper flex grow flex-col">
            <header class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background border-b border-border" id="header">
                <div class="kt-container-fixed flex justify-between items-stretch lg:gap-4" id="headerContainer">
                    <div class="flex gap-2.5 lg:hidden items-center -ms-1">
                        <a class="shrink-0 inline-flex items-center justify-center size-9 rounded-lg bg-primary text-primary-foreground" :href="pages.dashboard">
                            <i class="ki-filled ki-whatsapp text-xl"></i>
                        </a>
                        <button class="kt-btn kt-btn-icon kt-btn-ghost" type="button" aria-label="Abrir menu" @click="sidebarOpen = true">
                            <i class="ki-filled ki-menu"></i>
                        </button>
                    </div>

                    <div class="hidden md:flex items-center gap-2.5 grow max-w-[520px]">
                        <div class="kt-input kt-input-sm">
                            <i class="ki-filled ki-magnifier text-muted-foreground"></i>
                            <input placeholder="Buscar instâncias, mensagens ou contatos" type="search">
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.developers">
                            <i class="ki-filled ki-code"></i>
                            APIs
                        </a>
                        <span class="kt-badge kt-badge-light kt-badge-success hidden sm:inline-flex">
                            <span class="kt-badge-dot"></span>
                            Produção
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center justify-center size-9 rounded-full bg-accent text-mono text-xs font-semibold">
                                {{ initials(user.name) }}
                            </span>
                            <form :action="routes.logout" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="submit" aria-label="Sair">
                                    <i class="ki-filled ki-exit-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="grow pt-5" id="content" role="content">
                <div class="kt-container-fixed">
                    <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
                        <div class="flex flex-col justify-center gap-2">
                            <h1 class="text-xl font-medium leading-none text-mono">{{ payload.title }}</h1>
                            <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">{{ payload.description }}</div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <a v-if="page === 'dashboard'" class="kt-btn kt-btn-primary" :href="pages.instances">
                                <i class="ki-filled ki-plus"></i>
                                Nova instância
                            </a>
                            <a v-if="page === 'developers'" class="kt-btn kt-btn-outline" :href="pages.apiKeys">
                                <i class="ki-filled ki-key"></i>
                                Emitir chave
                            </a>
                        </div>
                    </div>

                    <div v-if="flash.status" class="kt-alert kt-alert-success mb-5">
                        <div class="kt-alert-title">{{ flash.status }}</div>
                    </div>
                    <div v-if="flash.apiToken" class="kt-alert kt-alert-primary mb-5">
                        <div class="grid gap-2">
                            <div class="kt-alert-title">Chave criada. Copie o token agora.</div>
                            <code class="waflow-code">{{ flash.apiToken }}</code>
                        </div>
                    </div>
                    <div v-if="flash.webhookSecret" class="kt-alert kt-alert-primary mb-5">
                        <div class="grid gap-2">
                            <div class="kt-alert-title">Segredo criado. Copie agora.</div>
                            <code class="waflow-code">{{ flash.webhookSecret }}</code>
                        </div>
                    </div>
                    <div v-if="flash.portalToken" class="kt-alert kt-alert-primary mb-5">
                        <div class="grid gap-2">
                            <div class="kt-alert-title">Token do portal. Copie agora para concluir a verificação.</div>
                            <code class="waflow-code">{{ flash.portalToken }}</code>
                        </div>
                    </div>
                    <div v-if="errors.length" class="kt-alert kt-alert-destructive mb-5">
                        <div class="kt-alert-title">{{ errors[0] }}</div>
                    </div>

                    <section v-if="page === 'dashboard'">
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
                                            <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent">
                                                <i class="ki-filled ki-whatsapp text-xl text-primary"></i>
                                            </span>
                                            <div>
                                                <div class="text-sm font-medium text-mono">OpenWA Engine</div>
                                                <div class="text-xs text-secondary-foreground">Execução das sessões WhatsApp</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span v-if="pageData.openWa?.latency_ms" class="text-xs text-secondary-foreground">{{ pageData.openWa.latency_ms }} ms</span>
                                            <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.openWa?.ok ? 'success' : 'destructive'}`">{{ pageData.openWa?.label }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent">
                                                <i class="ki-filled ki-data text-xl text-primary"></i>
                                            </span>
                                            <div>
                                                <div class="text-sm font-medium text-mono">PostgreSQL</div>
                                                <div class="text-xs text-secondary-foreground">Dados SaaS e schema OpenWA</div>
                                            </div>
                                        </div>
                                        <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.databaseOnline ? 'success' : 'destructive'}`">{{ pageData.databaseOnline ? 'Operacional' : 'Indisponível' }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-flex items-center justify-center size-10 rounded-lg bg-accent">
                                                <i class="ki-filled ki-flash-circle text-xl text-primary"></i>
                                            </span>
                                            <div>
                                                <div class="text-sm font-medium text-mono">Redis</div>
                                                <div class="text-xs text-secondary-foreground">Filas, cache e sessões do painel</div>
                                            </div>
                                        </div>
                                        <span class="kt-badge kt-badge-light" :class="`kt-badge-${pageData.redisConfigured ? 'success' : 'warning'}`">{{ pageData.redisConfigured ? 'Configurado' : 'Local' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <div>
                                        <h3 class="kt-card-title">Uso do plano</h3>
                                        <p class="kt-card-description">Ciclo atual</p>
                                    </div>
                                </div>
                                <div class="kt-card-content p-5 grid gap-4">
                                    <div v-for="(item, key) in usage" :key="key" class="grid gap-2">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-secondary-foreground">{{ usageLabels[key] || key }}</span>
                                            <span class="text-mono font-medium">{{ formatNumber(item.used) }}/{{ formatNumber(item.limit) }}</span>
                                        </div>
                                        <div class="waflow-progress" :aria-label="`${usageLabels[key] || key}: ${item.percent}%`">
                                            <span :style="{ width: progressWidth(item) }"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="waflow-grid waflow-grid-2">
                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Instâncias recentes</h3>
                                    <a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.instances">Ver todas</a>
                                </div>
                                <div class="kt-card-table">
                                    <div class="waflow-table-wrap waflow-table-compact">
                                        <table class="kt-table">
                                            <thead>
                                                <tr>
                                                    <th>Nome</th>
                                                    <th>Status</th>
                                                    <th>Telefone</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="instance in pageData.recentInstances" :key="instance.uuid">
                                                    <td class="font-medium text-mono">{{ instance.name }}</td>
                                                    <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ instance.status_label }}</span></td>
                                                    <td>{{ instance.phone_number || '-' }}</td>
                                                </tr>
                                                <tr v-if="!pageData.recentInstances?.length">
                                                    <td colspan="3" class="text-center text-secondary-foreground py-8">Nenhuma instância criada.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Eventos recentes</h3>
                                    <a class="kt-btn kt-btn-sm kt-btn-outline" :href="pages.messages">Abrir mensagens</a>
                                </div>
                                <div class="kt-card-table">
                                    <div class="waflow-table-wrap waflow-table-compact">
                                        <table class="kt-table">
                                            <thead>
                                                <tr>
                                                    <th>Destino</th>
                                                    <th>Status</th>
                                                    <th>Quando</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="message in pageData.recentMessages" :key="message.uuid">
                                                    <td class="font-medium text-mono">{{ message.to || message.from || '-' }}</td>
                                                    <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ message.status }}</span></td>
                                                    <td>{{ message.created_human }}</td>
                                                </tr>
                                                <tr v-if="!pageData.recentMessages?.length">
                                                    <td colspan="3" class="text-center text-secondary-foreground py-8">Nenhuma mensagem registrada.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-else-if="page === 'instances'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <div>
                                    <h3 class="kt-card-title">Instâncias WhatsApp</h3>
                                    <p class="kt-card-description">Cada instância mantém uma sessão isolada no workspace.</p>
                                </div>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Sessão</th>
                                                <th>Telefone</th>
                                                <th>Status</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="instance in pageData.instances" :key="instance.uuid">
                                                <td class="font-medium text-mono">{{ instance.name }}</td>
                                                <td><code class="text-xs">{{ instance.session_name }}</code></td>
                                                <td>{{ instance.phone_number || '-' }}</td>
                                                <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(instance.status)}`">{{ instance.status_label }}</span></td>
                                                <td>
                                                    <div class="flex justify-end gap-2">
                                                        <form :action="instance.connect_url" method="post">
                                                            <input name="_token" type="hidden" :value="csrfToken">
                                                            <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">
                                                                <i class="ki-filled ki-scan-barcode"></i>
                                                                Conectar
                                                            </button>
                                                        </form>
                                                        <form :action="instance.disconnect_url" method="post">
                                                            <input name="_token" type="hidden" :value="csrfToken">
                                                            <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon" type="submit" aria-label="Desconectar">
                                                                <i class="ki-filled ki-disconnect"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!pageData.instances?.length">
                                                <td colspan="5" class="text-center text-secondary-foreground py-10">Nenhuma instância criada.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="waflow-card-stack">
                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Nova instância</h3>
                                </div>
                                <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('instances').store" method="post">
                                    <input name="_token" type="hidden" :value="csrfToken">
                                    <div class="grid gap-1">
                                        <label class="kt-form-label" for="name">Nome</label>
                                        <input id="name" class="kt-input" name="name" placeholder="Atendimento comercial" required>
                                    </div>
                                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar instância</button>
                                </form>
                            </div>

                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Leitura de QR</h3>
                                </div>
                                <div class="kt-card-content p-5">
                                    <div v-if="qrInstance" class="border border-border rounded-lg p-4 text-center">
                                        <img class="mx-auto size-56 max-w-full object-contain" :src="qrInstance.qr_code" :alt="`QR Code da instância ${qrInstance.name}`">
                                        <div class="text-sm font-medium text-mono mt-3">{{ qrInstance.name }}</div>
                                    </div>
                                    <div v-else class="border border-dashed border-border rounded-lg p-6 text-center">
                                        <i class="ki-filled ki-scan-barcode text-4xl text-muted-foreground"></i>
                                        <div class="text-sm font-medium text-mono mt-3">Nenhum QR pendente</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-else-if="page === 'flows'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Fluxos</h3>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Gatilho</th>
                                                <th>Status</th>
                                                <th>Última execução</th>
                                                <th>Execuções</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="flow in pageData.flows" :key="flow.uuid">
                                                <td class="font-medium text-mono">{{ flow.name }}</td>
                                                <td>{{ flow.trigger_label }}</td>
                                                <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(flow.status)}`">{{ flow.status }}</span></td>
                                                <td>{{ flow.last_run_human || '-' }}</td>
                                                <td>{{ flow.run_count || 0 }} / {{ flow.failure_count || 0 }} falhas</td>
                                                <td>
                                                    <div class="flex justify-end gap-2">
                                                        <form :action="flow.toggle_url" method="post">
                                                            <input name="_token" type="hidden" :value="csrfToken">
                                                            <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">{{ flow.status === 'active' ? 'Pausar' : 'Ativar' }}</button>
                                                        </form>
                                                        <form :action="flow.test_url" method="post">
                                                            <input name="_token" type="hidden" :value="csrfToken">
                                                            <button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Testar</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!pageData.flows?.length">
                                                <td colspan="6" class="text-center text-secondary-foreground py-10">Nenhum fluxo criado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Novo fluxo</h3>
                            </div>
                            <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('flows').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="name">Nome</label>
                                    <input id="name" class="kt-input" name="name" placeholder="Boas-vindas" required>
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="trigger_type">Gatilho</label>
                                    <select id="trigger_type" class="kt-input" name="trigger_type" required>
                                        <option value="message_received">Mensagem recebida</option>
                                        <option value="message_failed">Mensagem com falha</option>
                                        <option value="instance_connected">Instância conectada</option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="flow_status">Status inicial</label>
                                    <select id="flow_status" class="kt-input" name="status"><option value="draft">Rascunho</option><option value="active">Ativo</option></select>
                                </div>
                                <div class="border-t border-border pt-3 grid gap-3">
                                    <div class="text-sm font-medium text-mono">Condição opcional</div>
                                    <input class="kt-input" name="condition_field" placeholder="trigger.body">
                                    <select class="kt-input" name="condition_operator"><option value="contains">Contém</option><option value="equals">Igual</option><option value="not_equals">Diferente</option><option value="starts_with">Começa com</option><option value="ends_with">Termina com</option><option value="exists">Existe</option></select>
                                    <input class="kt-input" name="condition_value" placeholder="Valor esperado">
                                </div>
                                <div class="border-t border-border pt-3 grid gap-3">
                                    <div class="text-sm font-medium text-mono">Resposta automática</div>
                                    <select class="kt-input" name="instance_uuid">
                                        <option value="">Usar instância do evento</option>
                                        <option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid">{{ instance.name }}</option>
                                    </select>
                                    <input class="kt-input" name="recipient" placeholder="Vazio usa o remetente do evento">
                                    <textarea class="kt-input min-h-24" name="message_text" placeholder="Olá! Recebemos sua mensagem."></textarea>
                                </div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar fluxo</button>
                            </form>
                        </div>

                        <div class="kt-card waflow-span-3">
                            <div class="kt-card-header"><h3 class="kt-card-title">Execuções recentes</h3></div>
                            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                <thead><tr><th>Fluxo</th><th>Gatilho</th><th>Status</th><th>Data</th><th>Erro</th></tr></thead>
                                <tbody>
                                    <tr v-for="execution in pageData.executions || []" :key="execution.uuid">
                                        <td>{{ execution.flow_name }}</td><td>{{ execution.trigger_type }}</td>
                                        <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(execution.status)}`">{{ execution.status }}</span></td>
                                        <td>{{ execution.created_at_label }}</td><td class="max-w-[320px] truncate">{{ execution.error || '-' }}</td>
                                    </tr>
                                    <tr v-if="!pageData.executions?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhuma execução registrada.</td></tr>
                                </tbody>
                            </table></div></div>
                        </div>
                    </section>

                    <section v-else-if="page === 'messages'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <div>
                                    <h3 class="kt-card-title">Eventos de mensagens</h3>
                                    <p class="kt-card-description">Base consumida por filas, webhooks e relatórios.</p>
                                </div>
                                <span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.messages?.total || 0 }} eventos</span>
                            </div>
                            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                <thead><tr><th>UUID</th><th>Direção</th><th>Contato</th><th>Status</th><th>Instância</th><th>Data</th></tr></thead>
                                <tbody>
                                    <tr v-for="message in pageData.messages?.items" :key="message.uuid">
                                        <td><code class="text-xs">{{ message.uuid }}</code></td><td>{{ message.direction }}</td><td>{{ message.to || message.from || '-' }}</td>
                                        <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(message.status)}`">{{ message.status }}</span></td><td>{{ message.instance_name || '-' }}</td><td>{{ message.created_at_label }}</td>
                                    </tr>
                                    <tr v-if="!pageData.messages?.items?.length"><td colspan="6" class="text-center text-secondary-foreground py-10">Nenhuma mensagem registrada.</td></tr>
                                </tbody>
                            </table></div></div>
                            <div v-if="pageData.messages?.links?.length" class="kt-card-footer"><div class="flex flex-wrap gap-2"><a v-for="link in pageData.messages.links" :key="`${link.label}-${link.url}`" class="kt-btn kt-btn-sm" :class="link.active ? 'kt-btn-primary' : 'kt-btn-outline'" :href="link.url || '#'" :aria-disabled="!link.url">{{ link.label }}</a></div></div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header"><h3 class="kt-card-title">Enviar mensagem</h3></div>
                            <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('messages').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1"><label class="kt-form-label">Instância conectada</label><select class="kt-input" name="instance_uuid" required><option value="">Selecione</option><option v-for="instance in pageData.instances || []" :key="instance.uuid" :value="instance.uuid" :disabled="instance.status !== 'connected'">{{ instance.name }} — {{ instance.status_label }}</option></select></div>
                                <div class="grid gap-1"><label class="kt-form-label">Destino</label><input class="kt-input" name="to" placeholder="5562999999999" required></div>
                                <div class="grid gap-1"><label class="kt-form-label">Texto</label><textarea class="kt-input min-h-28" name="text" maxlength="4096" required></textarea></div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Adicionar à fila</button>
                            </form>
                        </div>
                    </section>

                    <section v-else-if="page === 'contacts'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Contatos</h3>
                                <span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.contacts?.total || 0 }} contatos</span>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Telefone</th>
                                                <th>WA ID</th>
                                                <th>Última atividade</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="contact in pageData.contacts?.items" :key="contact.uuid">
                                                <td class="font-medium text-mono">{{ contact.name || 'Sem nome' }}</td>
                                                <td>{{ contact.phone_number }}</td>
                                                <td>{{ contact.wa_id || '-' }}</td>
                                                <td>{{ contact.last_seen_human || '-' }}</td>
                                            </tr>
                                            <tr v-if="!pageData.contacts?.items?.length">
                                                <td colspan="4" class="text-center text-secondary-foreground py-10">Nenhum contato salvo.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-if="pageData.contacts?.links?.length" class="kt-card-footer">
                                <div class="flex flex-wrap gap-2">
                                    <a v-for="link in pageData.contacts.links" :key="`${link.label}-${link.url}`" class="kt-btn kt-btn-sm" :class="link.active ? 'kt-btn-primary' : 'kt-btn-outline'" :href="link.url || '#'" :aria-disabled="!link.url">
                                        {{ link.label }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Adicionar contato</h3>
                            </div>
                            <form class="kt-card-content p-5 grid gap-4" :action="routeGroup('contacts').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="name">Nome</label>
                                    <input id="name" class="kt-input" name="name" placeholder="Maria Souza">
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="phone_number">Telefone</label>
                                    <input id="phone_number" class="kt-input" name="phone_number" placeholder="+55 62 99999-9999" required>
                                </div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Salvar contato</button>
                            </form>
                        </div>
                    </section>

                    <section v-else-if="page === 'portals'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header"><h3 class="kt-card-title">Portais vinculados</h3><span class="kt-badge kt-badge-light kt-badge-primary">{{ pageData.portals?.length || 0 }} portais</span></div>
                            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                <thead><tr><th>Nome</th><th>Domínio</th><th>Status</th><th>Verificação</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                    <tr v-for="portal in pageData.portals || []" :key="portal.uuid">
                                        <td><a class="font-medium text-primary" :href="portal.url" target="_blank" rel="noopener">{{ portal.name }}</a></td><td>{{ portal.domain }}</td>
                                        <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(portal.status)}`">{{ portal.status }}</span></td><td>{{ portal.verified_at_label || '-' }}</td>
                                        <td><div v-if="permissions.manage_integrations" class="flex justify-end gap-2"><form v-if="portal.status !== 'verified'" class="flex gap-2" :action="portal.verify_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><input class="kt-input kt-input-sm max-w-44" name="verification_token" placeholder="Token" required><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Verificar</button></form><form :action="portal.delete_url" method="post" @submit="confirmSubmit($event, 'Remover este portal?')"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Remover</button></form></div></td>
                                    </tr>
                                    <tr v-if="!pageData.portals?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum portal cadastrado.</td></tr>
                                </tbody>
                            </table></div></div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header"><h3 class="kt-card-title">Adicionar portal</h3></div>
                            <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('portals').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1"><label class="kt-form-label">Nome</label><input class="kt-input" name="name" placeholder="Portal principal" required></div>
                                <div class="grid gap-1"><label class="kt-form-label">URL</label><input class="kt-input" name="url" type="url" placeholder="https://portal.com.br" required></div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Adicionar portal</button>
                            </form>
                            <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Seu perfil tem acesso somente à consulta dos portais.</div>
                        </div>
                    </section>

                    <section v-else-if="page === 'developers'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <div>
                                    <h3 class="kt-card-title">Endpoints v1</h3>
                                    <p class="kt-card-description">Contrato inicial para automação SaaS.</p>
                                </div>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Método</th>
                                                <th>Rota</th>
                                                <th>Uso</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="[method, path, usageText] in endpoints" :key="`${method}-${path}`">
                                                <td><span class="kt-badge kt-badge-light kt-badge-primary">{{ method }}</span></td>
                                                <td><code class="text-xs">{{ path }}</code></td>
                                                <td>{{ usageText }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="waflow-card-stack">
                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Autenticação</h3>
                                </div>
                                <div class="kt-card-content p-5 grid gap-3">
                                    <div class="text-sm text-secondary-foreground">Bearer token</div>
                                    <code class="waflow-code">Authorization: Bearer waflow_xxxxxxxxxxxxxxxxx</code>
                                    <div class="text-sm text-secondary-foreground">Header alternativo</div>
                                    <code class="waflow-code">X-Waflow-Key: waflow_xxxxxxxxxxxxxxxxx</code>
                                    <a class="kt-btn kt-btn-primary justify-center" :href="pageData.openapi_url">Baixar OpenAPI 3.1</a>
                                </div>
                            </div>

                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Exemplo</h3>
                                </div>
                                <div class="kt-card-content p-5">
                                    <code class="waflow-code">curl -X POST {{ pageData.messages_endpoint }}
  -H "Authorization: Bearer waflow_xxx"
  -H "Content-Type: application/json"
  -d '{"to":"+5562999999999","body":"Ola pelo Waflow"}'</code>
                                    <div class="text-sm text-secondary-foreground mt-4">Erros seguem o contrato <code>error.code</code>, <code>error.message</code>, <code>error.details</code> e <code>meta.request_id</code>.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-else-if="page === 'webhooks'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Webhooks</h3>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>URL</th>
                                                <th>Eventos</th>
                                                <th>Status</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="webhook in pageData.webhooks" :key="webhook.id">
                                                <td class="font-medium text-mono">{{ webhook.name }}</td>
                                                <td class="max-w-[280px] truncate">{{ webhook.url }}</td>
                                                <td>{{ webhook.events_label }}</td>
                                                <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(webhook.status)}`">{{ webhook.status }}</span></td>
                                                <td>
                                                    <form v-if="permissions.manage_integrations" class="flex justify-end" :action="webhook.delete_url" method="post" @submit="confirmSubmit($event, 'Remover este webhook?')">
                                                        <input name="_token" type="hidden" :value="csrfToken">
                                                        <input name="_method" type="hidden" value="delete">
                                                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="submit" aria-label="Remover webhook">
                                                            <i class="ki-filled ki-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <tr v-if="!pageData.webhooks?.length">
                                                <td colspan="5" class="text-center text-secondary-foreground py-10">Nenhum webhook configurado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Novo webhook</h3>
                            </div>
                            <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('webhooks').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="name">Nome</label>
                                    <input id="name" class="kt-input" name="name" placeholder="CRM principal" required>
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="url">URL</label>
                                    <input id="url" class="kt-input" name="url" placeholder="https://app.exemplo.com/webhooks/waflow" type="url" required>
                                </div>
                                <div class="grid gap-2">
                                    <label class="kt-label">
                                        <input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.received" checked>
                                        <span class="kt-checkbox-label">message.received</span>
                                    </label>
                                    <label class="kt-label">
                                        <input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="session.status">
                                        <span class="kt-checkbox-label">session.status</span>
                                    </label>
                                    <label class="kt-label">
                                        <input class="kt-checkbox kt-checkbox-sm" name="events[]" type="checkbox" value="message.failed">
                                        <span class="kt-checkbox-label">message.failed</span>
                                    </label>
                                </div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Criar webhook</button>
                            </form>
                            <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner, admin ou developer pode criar webhooks.</div>
                        </div>
                    </section>

                    <section v-else-if="page === 'api-keys'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <div>
                                    <h3 class="kt-card-title">Chaves emitidas</h3>
                                    <p class="kt-card-description">Use Bearer token ou header X-Waflow-Key.</p>
                                </div>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Prefixo</th>
                                                <th>Permissões</th>
                                                <th>Último uso</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="apiKey in pageData.apiKeys" :key="apiKey.id">
                                                <td class="font-medium text-mono">{{ apiKey.name }}</td>
                                                <td><code class="text-xs">{{ apiKey.token_prefix }}</code></td>
                                                <td>{{ apiKey.abilities_label }}</td>
                                                <td>{{ apiKey.last_used_human || '-' }}</td>
                                                <td>
                                                    <form v-if="permissions.manage_integrations" class="flex justify-end" :action="apiKey.delete_url" method="post" @submit="confirmSubmit($event, 'Revogar esta chave de API?')">
                                                        <input name="_token" type="hidden" :value="csrfToken">
                                                        <input name="_method" type="hidden" value="delete">
                                                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="submit" aria-label="Remover chave">
                                                            <i class="ki-filled ki-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <tr v-if="!pageData.apiKeys?.length">
                                                <td colspan="5" class="text-center text-secondary-foreground py-10">Nenhuma chave de API emitida.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Nova chave</h3>
                            </div>
                            <form v-if="permissions.manage_integrations" class="kt-card-content p-5 grid gap-4" :action="routeGroup('apiKeys').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="name">Nome</label>
                                    <input id="name" class="kt-input" name="name" placeholder="Integração ERP" required>
                                </div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Emitir chave</button>
                            </form>
                            <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Seu perfil não pode emitir chaves de API.</div>
                        </div>
                    </section>

                    <section v-else-if="page === 'team'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Membros</h3>
                            </div>
                            <div class="kt-card-table">
                                <div class="waflow-table-wrap">
                                    <table class="kt-table">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Email</th>
                                                <th>Papel</th>
                                                <th>Status</th>
                                                <th>Entrada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="member in pageData.members" :key="member.id">
                                                <td class="font-medium text-mono">{{ member.name }}</td>
                                                <td>{{ member.email }}</td>
                                                <td><span class="kt-badge kt-badge-light kt-badge-primary">{{ member.role }}</span></td>
                                                <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(member.status)}`">{{ member.status }}</span></td>
                                                <td>{{ member.joined_at_label || '-' }}</td>
                                            </tr>
                                            <tr v-if="!pageData.members?.length">
                                                <td colspan="5" class="text-center text-secondary-foreground py-10">Nenhum membro ativo.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Convidar membro</h3>
                            </div>
                            <form v-if="permissions.manage_team" class="kt-card-content p-5 grid gap-4" :action="routeGroup('team').store" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="email">Email</label>
                                    <input id="email" class="kt-input" name="email" placeholder="pessoa@empresa.com" type="email" required>
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="role">Papel</label>
                                    <select id="role" class="kt-input" name="role" required>
                                        <option value="admin">Admin</option>
                                        <option value="developer">Developer</option>
                                        <option value="support">Suporte</option>
                                        <option value="member">Membro</option>
                                    </select>
                                </div>
                                <button class="kt-btn kt-btn-primary justify-center" type="submit">Enviar convite</button>
                            </form>
                            <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode convidar membros.</div>
                        </div>

                        <div class="kt-card waflow-span-3">
                            <div class="kt-card-header"><h3 class="kt-card-title">Convites</h3></div>
                            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                <thead><tr><th>Email</th><th>Papel</th><th>Status</th><th>Expira em</th><th class="text-end">Ações</th></tr></thead>
                                <tbody>
                                    <tr v-for="invitation in pageData.invitations || []" :key="invitation.uuid">
                                        <td>{{ invitation.email }}</td><td>{{ invitation.role }}</td>
                                        <td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invitation.status)}`">{{ invitation.status }}</span></td>
                                        <td>{{ invitation.expires_at_label }}</td>
                                        <td><form v-if="permissions.manage_team && invitation.status === 'pending'" class="flex justify-end" :action="invitation.revoke_url" method="post"><input name="_token" type="hidden" :value="csrfToken"><input name="_method" type="hidden" value="delete"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Revogar</button></form></td>
                                    </tr>
                                    <tr v-if="!pageData.invitations?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum convite enviado.</td></tr>
                                </tbody>
                            </table></div></div>
                        </div>
                    </section>

                    <section v-else-if="page === 'billing'">
                        <div class="waflow-grid waflow-grid-3">
                            <div class="kt-card">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Plano atual</h3>
                                </div>
                                <div class="kt-card-content p-5 grid gap-4">
                                    <div>
                                        <div class="text-2xl font-semibold text-mono">{{ plan?.name || 'Growth' }}</div>
                                        <div class="text-sm text-secondary-foreground">{{ plan?.monthly_price_label || 'R$ 0,00' }}/mês</div>
                                    </div>
                                    <span class="kt-badge kt-badge-light w-fit" :class="`kt-badge-${badgeVariant(workspace.subscription_status || 'trialing')}`">{{ workspace.subscription_status || 'trialing' }}</span>
                                    <div class="text-sm text-secondary-foreground">Ciclo até {{ workspace.current_period_ends_at_label || '-' }}</div>
                                </div>
                            </div>

                            <div class="kt-card waflow-span-2">
                                <div class="kt-card-header">
                                    <h3 class="kt-card-title">Consumo</h3>
                                </div>
                                <div class="kt-card-content p-5 grid gap-5">
                                    <div v-for="(item, key) in usage" :key="key" class="grid gap-2">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-medium text-mono">{{ usageLabels[key] || key }}</span>
                                            <span class="text-secondary-foreground">{{ formatNumber(item.used) }} de {{ formatNumber(item.limit) }}</span>
                                        </div>
                                        <div class="waflow-progress">
                                            <span :style="{ width: progressWidth(item) }"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kt-card mt-5">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Recursos do plano</h3>
                            </div>
                            <div class="kt-card-content p-5 grid gap-3 md:grid-cols-2">
                                <div v-for="feature in plan?.features || []" :key="feature" class="flex items-center gap-2 text-sm">
                                    <i class="ki-filled ki-check-circle text-success"></i>
                                    <span>{{ feature }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="waflow-grid waflow-grid-3 mt-5">
                            <div class="kt-card">
                                <div class="kt-card-header"><h3 class="kt-card-title">Novo checkout</h3></div>
                                <form v-if="permissions.manage_billing" class="kt-card-content p-5 grid gap-4" :action="routeGroup('billing').checkout" method="post">
                                    <input name="_token" type="hidden" :value="csrfToken">
                                    <div class="grid gap-1"><label class="kt-form-label">Plano</label><select class="kt-input" name="plan_slug" required><option v-for="availablePlan in pageData.plans || []" :key="availablePlan.slug" :value="availablePlan.slug">{{ availablePlan.name }} — {{ availablePlan.monthly_price_label }}/mês</option></select></div>
                                    <div class="grid gap-1"><label class="kt-form-label">Pagamento</label><select class="kt-input" name="payment_method" required><option value="credit_card">Cartão de crédito</option><option value="debit_card">Cartão de débito</option><option value="pix">Pix</option></select></div>
                                    <div class="text-xs text-secondary-foreground">Integração: {{ pageData.provider_status === 'configured' ? 'provedor configurado' : 'provedor ainda será escolhido' }}</div>
                                    <button class="kt-btn kt-btn-primary justify-center" type="submit">Preparar checkout</button>
                                </form>
                                <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode alterar o plano.</div>
                            </div>

                            <div class="kt-card waflow-span-2">
                                <div class="kt-card-header"><h3 class="kt-card-title">Checkouts recentes</h3></div>
                                <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                    <thead><tr><th>Plano</th><th>Forma</th><th>Valor</th><th>Status</th><th>Data</th></tr></thead>
                                    <tbody><tr v-for="checkout in pageData.checkouts || []" :key="checkout.uuid"><td>{{ checkout.plan }}</td><td>{{ checkout.payment_method }}</td><td>{{ checkout.amount_label }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(checkout.status)}`">{{ checkout.status }}</span></td><td>{{ checkout.created_at_label }}</td></tr><tr v-if="!pageData.checkouts?.length"><td colspan="5" class="text-center text-secondary-foreground py-8">Nenhum checkout iniciado.</td></tr></tbody>
                                </table></div></div>
                            </div>
                        </div>

                        <div class="kt-card mt-5">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Faturas e renovação</h3>
                                <form v-if="permissions.manage_billing && workspace.cancel_at_period_end" :action="routeGroup('billing').resume" method="post"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-primary" type="submit">Reativar renovação</button></form>
                                <form v-else-if="permissions.manage_billing" :action="routeGroup('billing').cancel" method="post" @submit="confirmSubmit($event, 'Cancelar a renovação ao fim do ciclo?')"><input name="_token" type="hidden" :value="csrfToken"><button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Cancelar renovação</button></form>
                            </div>
                            <div class="kt-card-table"><div class="waflow-table-wrap"><table class="kt-table">
                                <thead><tr><th>Número</th><th>Valor</th><th>Vencimento</th><th>Status</th></tr></thead>
                                <tbody><tr v-for="invoice in pageData.invoices || []" :key="invoice.uuid"><td>{{ invoice.number }}</td><td>{{ invoice.amount_label }}</td><td>{{ invoice.due_at_label || '-' }}</td><td><span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(invoice.status)}`">{{ invoice.status }}</span></td></tr><tr v-if="!pageData.invoices?.length"><td colspan="4" class="text-center text-secondary-foreground py-8">As faturas aparecerão aqui após a ativação do provedor.</td></tr></tbody>
                            </table></div></div>
                        </div>
                    </section>

                    <section v-else-if="page === 'settings'" class="waflow-grid waflow-grid-3">
                        <div class="kt-card waflow-span-2">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Workspace</h3>
                            </div>
                            <form v-if="permissions.manage_settings" class="kt-card-content p-5 grid gap-4" :action="routeGroup('settings').update" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <input name="_method" type="hidden" value="patch">
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="name">Nome</label>
                                    <input id="name" class="kt-input" name="name" :value="workspace.name" required>
                                </div>
                                <div class="grid gap-1">
                                    <label class="kt-form-label" for="timezone">Timezone</label>
                                    <input id="timezone" class="kt-input" name="timezone" :value="workspace.timezone" required>
                                </div>
                                <div class="flex justify-end">
                                    <button class="kt-btn kt-btn-primary" type="submit">Salvar alterações</button>
                                </div>
                            </form>
                            <div v-else class="kt-card-content p-5 text-sm text-secondary-foreground">Somente owner ou admin pode alterar o workspace.</div>
                        </div>

                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Ambiente</h3>
                            </div>
                            <div class="kt-card-content p-5 grid gap-3 text-sm">
                                <div class="flex justify-between gap-3">
                                    <span class="text-secondary-foreground">Workspace</span>
                                    <span class="text-mono font-medium">{{ workspace.slug }}</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-secondary-foreground">Status</span>
                                    <span class="kt-badge kt-badge-light" :class="`kt-badge-${badgeVariant(workspace.status)}`">{{ workspace.status }}</span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-secondary-foreground">Plano</span>
                                    <span class="text-mono font-medium">{{ plan?.name || 'Growth' }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <footer class="kt-footer">
                <div class="kt-container-fixed">
                    <div class="flex flex-col md:flex-row justify-center md:justify-between items-center gap-3 py-5">
                        <div class="flex order-2 md:order-1 gap-2 font-normal text-sm">
                            <span class="text-secondary-foreground">2026©</span>
                            <span class="text-secondary-foreground">Waflow</span>
                        </div>
                        <nav class="flex order-1 md:order-2 gap-4 font-normal text-sm text-secondary-foreground">
                            <a class="hover:text-primary" :href="pages.developers">APIs</a>
                            <a class="hover:text-primary" :href="pages.billing">Plano</a>
                            <a class="hover:text-primary" :href="pages.settings">Configurações</a>
                        </nav>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
