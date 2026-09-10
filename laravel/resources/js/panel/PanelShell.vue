<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { copyText, formatNumber, initials, roleLabel, statusLabel } from './panelFormatters';
import { usePanelContext } from './usePanelContext';

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
});

const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const isDarkMode = ref(false);
const searchFocused = ref(false);
const searchInput = ref(null);
const mobileSearchInput = ref(null);
const mobileSearchOpen = ref(false);
const searchQuery = ref('');
const activeSearchIndex = ref(0);
const copiedSecret = ref('');
const copyFailed = ref(false);
const statusFlashVisible = ref(true);
const errorsVisible = ref(true);
const overviewMenu = ref(null);
const userMenu = ref(null);
const confirmation = ref({ open: false, form: null, message: '', submitter: null });
const currentYear = new Date().getFullYear();
let searchBlurTimer;
let copiedSecretTimer;
const {
    payload,
    page,
    routes,
    pages,
    csrfToken,
    flash,
    errors,
    workspace,
    workspaces,
    plan,
    platformHealth,
    usage,
    user,
    navSections,
} = usePanelContext(props);

const usageLabels = {
    api_keys: 'Chaves API',
    instances: 'Instâncias',
    messages: 'Mensagens',
    portals: 'Sites/domínios',
    webhooks: 'Webhooks',
};

const isClientConsole = computed(() => !payload.value.console || payload.value.console === 'client');
const contextLinks = computed(() => payload.value.contextLinks || []);
const contextSwitcherLabel = computed(() => (payload.value.console === 'partner' ? 'Trocar parceiro' : 'Trocar workspace'));

const usageEntries = computed(() => Object.entries(usage.value || {}).map(([key, item]) => ({
    key,
    label: usageLabels[key] || key,
    ...item,
})));

const platformOperational = computed(() => (
    Boolean(platformHealth.value.openWa?.ok) && Boolean(platformHealth.value.databaseOnline)
));

const fieldErrors = computed(() => flash.value.fieldErrors || {});
const oldInput = computed(() => flash.value.old || {});

const searchableItems = computed(() => navSections.value.flatMap((section) => (
    section.items.map((item) => ({ ...item, section: section.label }))
)));

const searchResults = computed(() => {
    const query = normalizeSearch(searchQuery.value);

    if (!query) {
        return [];
    }

    return searchableItems.value
        .filter((item) => normalizeSearch(`${item.label} ${item.section}`).includes(query))
        .slice(0, 6);
});

function normalizeSearch(value) {
    return `${value || ''}`
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase('pt-BR')
        .trim();
}

function usageTone(percent) {
    if (Number(percent) >= 95) return 'is-danger';
    if (Number(percent) >= 80) return 'is-warning';

    return '';
}

function submitSearch() {
    const result = searchResults.value[activeSearchIndex.value] || searchResults.value[0];

    if (result) {
        window.location.assign(result.url);
    }
}

function handleSearchKeydown(event) {
    if (!searchResults.value.length) {
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeSearchIndex.value = (activeSearchIndex.value + 1) % searchResults.value.length;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeSearchIndex.value = (activeSearchIndex.value - 1 + searchResults.value.length) % searchResults.value.length;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        submitSearch();
    }
}

function deferSearchBlur() {
    window.clearTimeout(searchBlurTimer);
    searchBlurTimer = window.setTimeout(() => {
        searchFocused.value = false;
    }, 120);
}

function closeSearch() {
    searchQuery.value = '';
    searchFocused.value = false;
    mobileSearchOpen.value = false;
    activeSearchIndex.value = 0;
    searchInput.value?.blur();
    mobileSearchInput.value?.blur();
}

async function openMobileSearch() {
    mobileSearchOpen.value = true;
    searchFocused.value = true;
    await nextTick();
    mobileSearchInput.value?.focus();
}

function toggleSidebarCollapse() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem('waflow-sidebar-collapsed', sidebarCollapsed.value ? 'true' : 'false');
}

function toggleTheme() {
    isDarkMode.value = !isDarkMode.value;
    const theme = isDarkMode.value ? 'dark' : 'light';

    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.classList.add(theme);
    document.documentElement.dataset.ktThemeMode = theme;
    document.documentElement.style.colorScheme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isDarkMode.value ? '#09090b' : '#ffffff');
    localStorage.setItem('kt-theme', theme);
}

async function copySecret(name, value) {
    if (!value) {
        return;
    }

    try {
        await copyText(value);
        copiedSecret.value = name;
        copyFailed.value = false;
    } catch {
        copiedSecret.value = '';
        copyFailed.value = true;
    }
    window.clearTimeout(copiedSecretTimer);
    copiedSecretTimer = window.setTimeout(() => {
        copiedSecret.value = '';
        copyFailed.value = false;
    }, 2000);
}

function handleGlobalKeydown(event) {
    if (event.key === 'Escape') {
        sidebarOpen.value = false;
        overviewMenu.value?.removeAttribute('open');
        userMenu.value?.removeAttribute('open');
        if (confirmation.value.open) {
            cancelConfirmation();
        }
        closeSearch();
    }

    if ((event.metaKey || event.ctrlKey) && event.key.toLocaleLowerCase() === 'k') {
        event.preventDefault();
        if (window.matchMedia('(min-width: 768px)').matches) {
            searchFocused.value = true;
            searchInput.value?.focus();
        } else {
            openMobileSearch();
        }
    }
}

function handleDetailsToggle(current) {
    if (!current?.open) {
        return;
    }

    [overviewMenu.value, userMenu.value].forEach((menu) => {
        if (menu && menu !== current) {
            menu.open = false;
        }
    });
}

function handleDocumentClick(event) {
    [overviewMenu.value, userMenu.value].forEach((menu) => {
        if (menu?.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
}

function markFormSubmitting(form, submitter = null) {
    if (!form || form.dataset.submitting === 'true') {
        return false;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const button = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('is-submitting');
        if (!button.classList.contains('kt-btn-icon')) {
            button.replaceChildren(document.createTextNode('Processando…'));
        }
    }

    return true;
}

function handleDocumentSubmit(event) {
    window.queueMicrotask(() => {
        if (!event.defaultPrevented) {
            markFormSubmitting(event.target, event.submitter);
        }
    });
}

function requestConfirmation(event) {
    confirmation.value = {
        open: true,
        form: event.detail?.form || null,
        message: event.detail?.message || 'Deseja confirmar esta ação?',
        submitter: event.detail?.submitter || null,
    };
}

function cancelConfirmation() {
    const submitter = confirmation.value.submitter;
    confirmation.value = { open: false, form: null, message: '', submitter: null };
    nextTick(() => submitter?.focus());
}

function approveConfirmation() {
    const form = confirmation.value.form;
    const submitter = confirmation.value.submitter;
    confirmation.value = { open: false, form: null, message: '', submitter: null };

    if (markFormSubmitting(form, submitter)) {
        HTMLFormElement.prototype.submit.call(form);
    }
}

function trapDialogFocus(event) {
    const focusable = [...event.currentTarget.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
    if (!focusable.length) {
        return;
    }

    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function hydrateFormState() {
    Object.entries(oldInput.value).forEach(([name, value]) => {
        const selectorName = window.CSS?.escape ? window.CSS.escape(name) : name;
        document.querySelectorAll(`[name="${selectorName}"], [name="${selectorName}[]"]`).forEach((field) => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = Array.isArray(value) ? value.includes(field.value) : `${value}` === field.value;
            } else {
                field.value = value ?? '';
            }
        });
    });

    Object.entries(fieldErrors.value).forEach(([name, messages]) => {
        const baseName = name.split('.')[0];
        const selectorName = window.CSS?.escape ? window.CSS.escape(baseName) : baseName;
        const field = document.querySelector(`[name="${selectorName}"], [name="${selectorName}[]"]`);
        if (!field || field.dataset.errorHydrated === 'true') {
            return;
        }

        const errorId = `field-error-${baseName.replace(/[^a-z0-9_-]/gi, '-')}`;
        field.dataset.errorHydrated = 'true';
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', errorId);
        const message = document.createElement('p');
        message.id = errorId;
        message.className = 'waflow-field-error';
        message.textContent = messages[0];
        field.insertAdjacentElement('afterend', message);
    });
}

watch(sidebarOpen, (open) => {
    document.body.classList.toggle('waflow-mobile-nav-open', open);
});

watch(sidebarCollapsed, (collapsed) => {
    document.body.classList.toggle('kt-sidebar-collapse', collapsed);
});

watch(searchResults, () => {
    activeSearchIndex.value = 0;
});

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    sidebarCollapsed.value = localStorage.getItem('waflow-sidebar-collapsed') === 'true';
    window.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('click', handleDocumentClick);
    window.addEventListener('waflow:confirm-submit', requestConfirmation);
    document.addEventListener('submit', handleDocumentSubmit);
    nextTick(hydrateFormState);
});

onBeforeUnmount(() => {
    window.clearTimeout(searchBlurTimer);
    window.clearTimeout(copiedSecretTimer);
    window.removeEventListener('keydown', handleGlobalKeydown);
    window.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('waflow:confirm-submit', requestConfirmation);
    document.removeEventListener('submit', handleDocumentSubmit);
    document.body.classList.remove('waflow-mobile-nav-open');
    document.body.classList.remove('kt-sidebar-collapse');
});
</script>

<template>
    <button v-if="sidebarOpen" class="waflow-sidebar-overlay lg:hidden" type="button" aria-label="Fechar navegação" @click="sidebarOpen = false"></button>

    <div v-if="mobileSearchOpen" class="waflow-dialog-backdrop" role="presentation" @mousedown.self="closeSearch">
        <section class="waflow-command-dialog" role="dialog" aria-modal="true" aria-labelledby="mobile-search-title" @keydown.tab="trapDialogFocus">
            <div class="waflow-dialog-header">
                <div>
                    <h2 id="mobile-search-title">Buscar no painel</h2>
                    <p>Navegue rapidamente para qualquer área.</p>
                </div>
                <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Fechar busca" @click="closeSearch"><i class="ki-filled ki-cross"></i></button>
            </div>
            <form class="waflow-command-search" role="search" @submit.prevent="submitSearch">
                <label class="kt-input">
                    <i class="ki-filled ki-magnifier text-muted-foreground"></i>
                    <input ref="mobileSearchInput" v-model="searchQuery" type="search" autocomplete="off" placeholder="Digite o nome de uma área" aria-controls="panel-mobile-search-results" :aria-expanded="Boolean(searchQuery)" @keydown="handleSearchKeydown">
                </label>
            </form>
            <div id="panel-mobile-search-results" class="waflow-command-results" role="listbox">
                <a v-for="(item, index) in searchResults" :key="item.route" class="waflow-search-result" :class="{ 'is-active': activeSearchIndex === index }" :href="item.url" role="option" :aria-selected="activeSearchIndex === index" @mouseenter="activeSearchIndex = index">
                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-primary"><i class="ki-filled" :class="item.icon"></i></span>
                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-foreground">{{ item.label }}</span><span class="block truncate text-xs text-muted-foreground">{{ item.section }}</span></span>
                </a>
                <div v-if="searchQuery && !searchResults.length" class="waflow-command-empty">Nenhuma área encontrada.</div>
                <div v-else-if="!searchQuery" class="waflow-command-empty">Comece digitando para buscar.</div>
            </div>
        </section>
    </div>

    <div class="flex grow">
        <aside
            id="sidebar"
            class="kt-sidebar fixed top-0 bottom-0 z-20 flex flex-col items-stretch shrink-0 waflow-sidebar-mobile lg:translate-x-0"
            :class="{ 'is-open': sidebarOpen }"
            aria-label="Navegação principal"
        >
            <div id="sidebar_header" class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0">
                <a class="flex items-center gap-2.5" :href="pages.dashboard" :title="sidebarCollapsed ? 'Waflow — Visão geral' : undefined">
                    <span class="inline-flex items-center justify-center size-9 rounded-lg bg-primary text-primary-foreground">
                        <i class="ki-filled ki-whatsapp text-xl"></i>
                    </span>
                    <span class="default-logo text-lg font-semibold text-mono">Waflow</span>
                </a>
                <button class="waflow-sidebar-collapse-button kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-controls="sidebar_menu" :aria-expanded="!sidebarCollapsed" :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'" @click="toggleSidebarCollapse">
                    <i class="ki-filled" :class="sidebarCollapsed ? 'ki-double-right-arrow' : 'ki-double-left-arrow'"></i>
                </button>
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

            <div class="waflow-workspace-switcher px-3 lg:px-5 pt-3">
                <div class="waflow-workspace-card rounded-lg px-3 py-3">
                    <div class="flex items-center gap-2.5" :title="`${workspace.name} · ${roleLabel(workspace.member_role)}`">
                        <span class="inline-flex items-center justify-center size-9 rounded-full bg-primary text-primary-foreground text-xs font-semibold">
                            {{ initials(workspace.name) }}
                        </span>
                        <div class="waflow-workspace-meta min-w-0">
                            <div class="text-sm font-semibold text-mono truncate">{{ workspace.name }}</div>
                            <div class="text-xs text-muted-foreground truncate">{{ plan?.name || 'Growth' }} · {{ roleLabel(workspace.member_role) }}</div>
                        </div>
                    </div>
                    <details v-if="workspaces.length > 1" class="waflow-workspace-options mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-primary"><i class="ki-filled ki-switch"></i> {{ contextSwitcherLabel }}</summary>
                        <div class="grid gap-1 mt-2">
                            <form v-for="available in workspaces.filter((item) => !item.active)" :key="available.uuid" :action="available.select_url" method="post">
                                <input name="_token" type="hidden" :value="csrfToken">
                                <button class="kt-btn kt-btn-sm kt-btn-ghost w-full justify-start" type="submit">{{ available.name }} · {{ roleLabel(available.role) }}</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <div id="sidebar_content" class="kt-sidebar-content flex grow shrink-0 py-5 pe-2">
                <div id="sidebar_scrollable" class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3">
                    <nav id="sidebar_menu" class="kt-menu flex flex-col grow gap-1">
                        <template v-for="section in navSections" :key="section.label">
                            <div class="waflow-menu-section-heading kt-menu-item pt-2.25 pb-px">
                                <span class="kt-menu-heading uppercase text-xs font-medium text-muted-foreground ps-[10px] pe-[10px]">{{ section.label }}</span>
                            </div>
                            <div v-for="item in section.items" :key="item.route" class="kt-menu-item" :class="{ active: item.active }">
                                <a class="kt-menu-link items-center grow rounded-lg gap-[10px] ps-[10px] pe-[10px] py-[8px]" :href="item.url" :aria-current="item.active ? 'page' : undefined" :title="sidebarCollapsed ? item.label : undefined">
                                    <span class="kt-menu-icon items-start text-muted-foreground w-[15px]">
                                        <i class="ki-filled text-md" :class="item.route === 'panel.portals' ? 'ki-geolocation' : item.icon"></i>
                                    </span>
                                    <span class="kt-menu-title text-xs" :class="item.active ? 'font-semibold' : 'font-medium'">{{ item.label }}</span>
                                </a>
                            </div>
                        </template>
                    </nav>
                </div>
            </div>
        </aside>

        <div class="kt-wrapper flex grow flex-col">
            <header id="header" class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background border-b border-border">
                <div id="headerContainer" class="kt-container-fluid waflow-content-container waflow-header-container">
                    <div class="flex gap-2.5 lg:hidden items-center -ms-1">
                        <a class="shrink-0 inline-flex items-center justify-center size-9 rounded-lg bg-primary text-primary-foreground" :href="pages.dashboard">
                            <i class="ki-filled ki-whatsapp text-xl"></i>
                        </a>
                        <button class="kt-btn kt-btn-icon kt-btn-ghost" type="button" aria-label="Abrir menu" aria-controls="sidebar" :aria-expanded="sidebarOpen" @click="sidebarOpen = true">
                            <i class="ki-filled ki-menu"></i>
                        </button>
                    </div>

                    <form class="waflow-search waflow-header-search" role="search" @submit.prevent="submitSearch">
                        <label class="sr-only" for="panel-search">Buscar no painel</label>
                        <div class="kt-input kt-input-md">
                            <i class="ki-filled ki-magnifier text-muted-foreground"></i>
                            <input
                                id="panel-search"
                                ref="searchInput"
                                v-model="searchQuery"
                                aria-autocomplete="list"
                                aria-controls="panel-search-results"
                                :aria-expanded="searchFocused && Boolean(searchQuery)"
                                autocomplete="off"
                                placeholder="Buscar uma área do painel"
                                type="search"
                                @focus="searchFocused = true"
                                @blur="deferSearchBlur"
                                @keydown="handleSearchKeydown"
                            >
                            <kbd class="hidden lg:inline-flex text-[10px] text-muted-foreground border border-border rounded px-1.5 py-0.5">⌘K</kbd>
                        </div>
                        <div v-if="searchFocused && searchQuery" id="panel-search-results" class="waflow-search-results" role="listbox">
                            <a
                                v-for="(item, index) in searchResults"
                                :key="item.route"
                                class="waflow-search-result"
                                :class="{ 'is-active': activeSearchIndex === index }"
                                :href="item.url"
                                role="option"
                                :aria-selected="activeSearchIndex === index"
                                @mouseenter="activeSearchIndex = index"
                                @mousedown.prevent
                            >
                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-accent text-primary">
                                    <i class="ki-filled" :class="item.icon"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-foreground">{{ item.label }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ item.section }}</span>
                                </span>
                            </a>
                            <div v-if="!searchResults.length" class="px-4 py-4 text-sm text-muted-foreground">Nenhuma área encontrada.</div>
                        </div>
                    </form>

                    <div class="waflow-header-actions">
                        <button class="waflow-mobile-search-trigger kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Buscar no painel" title="Buscar no painel" @click="openMobileSearch">
                            <i class="ki-filled ki-magnifier"></i>
                        </button>
                        <details v-if="isClientConsole" ref="overviewMenu" class="waflow-overview-menu" @toggle="handleDetailsToggle($event.currentTarget)">
                            <summary class="kt-btn kt-btn-md kt-btn-outline" aria-label="Abrir resumo da plataforma e do plano">
                                <i class="ki-filled ki-shield-search"></i>
                                <span class="waflow-overview-label">Uso</span>
                                <span class="kt-badge-dot" :class="platformOperational ? 'is-active' : 'is-inactive'"></span>
                            </summary>
                            <div class="waflow-overview-panel">
                                <div class="waflow-overview-heading">
                                    <div>
                                        <div class="font-semibold text-foreground">Saúde e uso</div>
                                        <div class="text-xs text-muted-foreground">Resumo operacional do workspace</div>
                                    </div>
                                    <span class="kt-badge kt-badge-light" :class="platformOperational ? 'kt-badge-success' : 'kt-badge-destructive'">{{ platformOperational ? 'Operacional' : 'Com atenção' }}</span>
                                </div>
                                <section class="waflow-overview-section" aria-labelledby="overview-health-title">
                                    <div class="waflow-overview-section-heading">
                                        <span class="waflow-overview-icon"><i class="ki-filled ki-heart-circle"></i></span>
                                        <span><strong id="overview-health-title">Saúde da plataforma</strong><small>Serviços essenciais do Waflow</small></span>
                                    </div>
                                    <div class="waflow-overview-service">
                                        <span><i class="ki-filled ki-whatsapp"></i>OpenWA Engine</span>
                                        <span><small v-if="platformHealth.openWa?.latency_ms">{{ platformHealth.openWa.latency_ms }} ms</small><span class="kt-badge kt-badge-light" :class="`kt-badge-${platformHealth.openWa?.ok ? 'success' : 'destructive'}`">{{ platformHealth.openWa?.label || 'Indisponível' }}</span></span>
                                    </div>
                                    <div class="waflow-overview-service">
                                        <span><i class="ki-filled ki-data"></i>PostgreSQL</span>
                                        <span class="kt-badge kt-badge-light" :class="`kt-badge-${platformHealth.databaseOnline ? 'success' : 'destructive'}`">{{ platformHealth.databaseOnline ? 'Operacional' : 'Indisponível' }}</span>
                                    </div>
                                    <div class="waflow-overview-service">
                                        <span><i class="ki-filled ki-flash-circle"></i>Redis</span>
                                        <span class="kt-badge kt-badge-light" :class="`kt-badge-${platformHealth.redisConfigured ? 'success' : 'warning'}`">{{ platformHealth.redisConfigured ? 'Configurado' : 'Local' }}</span>
                                    </div>
                                </section>
                                <section class="waflow-overview-section waflow-overview-usage" aria-labelledby="overview-usage-title">
                                    <div class="waflow-overview-section-heading">
                                        <span class="waflow-overview-icon"><i class="ki-filled ki-chart-simple"></i></span>
                                        <span><strong id="overview-usage-title">Uso do plano</strong><small>{{ plan?.name || 'Plano atual' }} · ciclo atual</small></span>
                                    </div>
                                    <div v-for="item in usageEntries" :key="item.key" class="waflow-overview-meter">
                                        <div><span>{{ item.label }}</span><span>{{ formatNumber(item.used) }}/{{ formatNumber(item.limit) }}</span></div>
                                        <div class="waflow-progress" :class="usageTone(item.percent)" role="progressbar" :aria-label="item.label" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="item.percent"><span :style="{ width: `${item.percent}%` }"></span></div>
                                    </div>
                                </section>
                            </div>
                        </details>
                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" :aria-label="isDarkMode ? 'Usar tema claro' : 'Usar tema escuro'" :title="isDarkMode ? 'Usar tema claro' : 'Usar tema escuro'" @click="toggleTheme">
                            <i class="ki-filled" :class="isDarkMode ? 'ki-sun' : 'ki-moon'"></i>
                        </button>
                        <details ref="userMenu" class="waflow-user-menu" @toggle="handleDetailsToggle($event.currentTarget)">
                            <summary aria-label="Abrir menu da conta">
                                <span class="inline-flex items-center justify-center size-9 rounded-full bg-accent text-mono text-xs font-semibold">
                                    {{ initials(user.name) }}
                                </span>
                                <span class="waflow-user-identity min-w-0 max-w-36">
                                    <span class="block truncate text-sm font-medium text-foreground">{{ user.name }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ user.email }}</span>
                                </span>
                                <i class="waflow-user-chevron ki-filled ki-down text-xs text-muted-foreground"></i>
                            </summary>
                            <div class="waflow-user-menu-panel">
                                <div class="border-b border-border px-4 py-3">
                                    <div class="truncate text-sm font-semibold text-foreground">{{ user.name }}</div>
                                    <div class="truncate text-xs text-muted-foreground">{{ user.email }}</div>
                                </div>
                                <nav class="grid gap-1 p-2" aria-label="Conta">
                                    <div class="px-2 pt-1 text-xs text-muted-foreground">{{ roleLabel(workspace.member_role) }} em {{ workspace.name }}</div>
                                    <a v-if="isClientConsole" class="kt-btn kt-btn-sm kt-btn-ghost justify-start" :href="pages.settings"><i class="ki-filled ki-setting-2"></i>Configurações</a>
                                    <a v-if="isClientConsole" class="kt-btn kt-btn-sm kt-btn-ghost justify-start" :href="pages.billing"><i class="ki-filled ki-credit-cart"></i>Plano e consumo</a>
                                    <a v-for="link in contextLinks" :key="link.url" class="kt-btn kt-btn-sm kt-btn-ghost justify-start" :href="link.url"><i class="ki-filled" :class="link.icon"></i>{{ link.label }}</a>
                                </nav>
                                <form class="border-t border-border p-2" :action="routes.logout" method="post">
                                    <input name="_token" type="hidden" :value="csrfToken">
                                    <button class="kt-btn kt-btn-sm kt-btn-ghost w-full justify-start" type="submit">
                                        <i class="ki-filled ki-exit-right"></i>
                                        Sair
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </header>

            <main id="content" class="grow py-6" aria-labelledby="page-title">
                <div class="kt-container-fluid waflow-content-container waflow-content-header">
                    <div class="flex flex-wrap items-start lg:items-end justify-between gap-4 pb-6">
                        <div class="flex flex-col justify-center gap-2">
                            <h1 id="page-title" class="text-2xl font-semibold tracking-tight leading-none text-mono">{{ payload.title }}</h1>
                            <p class="max-w-3xl text-sm font-normal leading-relaxed text-muted-foreground">{{ payload.description }}</p>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <slot name="page-actions"></slot>
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

                    <div v-if="flash.status && statusFlashVisible" class="kt-alert kt-alert-light kt-alert-success mb-5 waflow-dismissible-alert" role="status">
                        <div class="kt-alert-title">{{ flash.status }}</div>
                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Fechar aviso" @click="statusFlashVisible = false"><i class="ki-filled ki-cross"></i></button>
                    </div>
                    <div v-if="flash.apiToken" class="kt-alert kt-alert-light kt-alert-primary mb-5" role="status">
                        <div class="grid grow gap-2">
                            <div class="kt-alert-title">Chave criada. Copie o token agora.</div>
                            <code class="waflow-code">{{ flash.apiToken }}</code>
                            <button class="kt-btn kt-btn-sm kt-btn-outline w-fit" type="button" @click="copySecret('api', flash.apiToken)">
                                <i class="ki-filled" :class="copiedSecret === 'api' ? 'ki-check' : 'ki-copy'"></i>
                                {{ copiedSecret === 'api' ? 'Copiado' : 'Copiar token' }}
                            </button>
                        </div>
                    </div>
                    <div v-if="flash.webhookSecret" class="kt-alert kt-alert-light kt-alert-primary mb-5" role="status">
                        <div class="grid grow gap-2">
                            <div class="kt-alert-title">Segredo criado. Copie agora.</div>
                            <code class="waflow-code">{{ flash.webhookSecret }}</code>
                            <button class="kt-btn kt-btn-sm kt-btn-outline w-fit" type="button" @click="copySecret('webhook', flash.webhookSecret)">
                                <i class="ki-filled" :class="copiedSecret === 'webhook' ? 'ki-check' : 'ki-copy'"></i>
                                {{ copiedSecret === 'webhook' ? 'Copiado' : 'Copiar segredo' }}
                            </button>
                        </div>
                    </div>
                    <div v-if="flash.portalToken" class="kt-alert kt-alert-light kt-alert-primary mb-5" role="status">
                        <div class="grid grow gap-2">
                            <div class="kt-alert-title">Token do site/domínio. Copie agora para concluir a verificação.</div>
                            <code class="waflow-code">{{ flash.portalToken }}</code>
                            <button class="kt-btn kt-btn-sm kt-btn-outline w-fit" type="button" @click="copySecret('portal', flash.portalToken)">
                                <i class="ki-filled" :class="copiedSecret === 'portal' ? 'ki-check' : 'ki-copy'"></i>
                                {{ copiedSecret === 'portal' ? 'Copiado' : 'Copiar token' }}
                            </button>
                        </div>
                    </div>
                    <div v-if="errors.length && errorsVisible" class="kt-alert kt-alert-light kt-alert-destructive mb-5 waflow-dismissible-alert" role="alert">
                        <div class="grow">
                            <div class="kt-alert-title">Revise os campos destacados</div>
                            <ul class="waflow-error-list"><li v-for="message in errors" :key="message">{{ message }}</li></ul>
                        </div>
                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Fechar erros" @click="errorsVisible = false"><i class="ki-filled ki-cross"></i></button>
                    </div>
                    <div v-if="copyFailed" class="kt-alert kt-alert-light kt-alert-destructive mb-5" role="alert">
                        <div class="kt-alert-title">Não foi possível copiar automaticamente. Selecione o conteúdo e copie manualmente.</div>
                    </div>

                    <slot></slot>
                </div>
            </main>

            <footer class="kt-footer border-t border-border">
                <div class="kt-container-fluid waflow-content-container">
                    <div class="flex flex-col md:flex-row justify-center md:justify-between items-center gap-3 py-5">
                        <div class="flex order-2 md:order-1 gap-2 font-normal text-xs">
                            <span class="text-secondary-foreground">{{ currentYear }}©</span>
                            <span class="text-secondary-foreground">Waflow</span>
                        </div>
                        <nav v-if="isClientConsole" class="flex order-1 md:order-2 gap-4 font-normal text-xs text-secondary-foreground">
                            <a class="hover:text-primary" :href="pages.developers">APIs</a>
                            <a class="hover:text-primary" :href="pages.billing">Plano</a>
                            <a class="hover:text-primary" :href="pages.settings">Configurações</a>
                        </nav>
                        <nav v-else class="flex order-1 md:order-2 gap-4 font-normal text-sm text-secondary-foreground"><a class="hover:text-primary" :href="pages.dashboard">Visão geral</a><a v-for="link in contextLinks" :key="link.url" class="hover:text-primary" :href="link.url">{{ link.label }}</a></nav>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <div v-if="confirmation.open" class="waflow-dialog-backdrop" role="presentation" @mousedown.self="cancelConfirmation">
        <section class="waflow-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-description" @keydown.tab="trapDialogFocus">
            <span class="waflow-confirm-icon"><i class="ki-filled ki-information-2"></i></span>
            <div>
                <h2 id="confirm-title">Confirmar ação</h2>
                <p id="confirm-description">{{ confirmation.message }}</p>
            </div>
            <div class="waflow-confirm-actions">
                <button class="kt-btn kt-btn-outline" type="button" @click="cancelConfirmation">Cancelar</button>
                <button class="kt-btn kt-btn-destructive" type="button" autofocus @click="approveConfirmation">Confirmar</button>
            </div>
        </section>
    </div>
</template>
