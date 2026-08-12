<script setup>
import { ref } from 'vue';
import { initials } from './panelFormatters';
import { usePanelContext } from './usePanelContext';

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
});

const sidebarOpen = ref(false);
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
    user,
    navSections,
} = usePanelContext(props);
</script>

<template>
    <div v-if="sidebarOpen" class="waflow-sidebar-overlay lg:hidden" @click="sidebarOpen = false"></div>

    <div class="flex grow">
        <aside
            id="sidebar"
            class="kt-sidebar bg-background border-e border-e-border fixed top-0 bottom-0 z-20 flex flex-col items-stretch shrink-0 waflow-sidebar-mobile lg:translate-x-0"
            :class="{ 'is-open': sidebarOpen }"
        >
            <div id="sidebar_header" class="kt-sidebar-header hidden lg:flex items-center relative justify-between px-3 lg:px-6 shrink-0">
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

            <div id="sidebar_content" class="kt-sidebar-content flex grow shrink-0 py-5 pe-2">
                <div id="sidebar_scrollable" class="kt-scrollable-y-hover grow shrink-0 flex ps-2 lg:ps-5 pe-1 lg:pe-3">
                    <nav id="sidebar_menu" class="kt-menu flex flex-col grow gap-1">
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
            <header id="header" class="kt-header fixed top-0 z-10 start-0 end-0 flex items-stretch shrink-0 bg-background border-b border-border">
                <div id="headerContainer" class="kt-container-fixed flex justify-between items-stretch lg:gap-4">
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

            <main id="content" class="grow pt-5" role="content">
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

                    <slot></slot>
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
