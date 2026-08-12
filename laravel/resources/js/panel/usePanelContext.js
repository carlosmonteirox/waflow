import { computed } from 'vue';

export function usePanelContext(props) {
    const payload = computed(() => props.payload || {});
    const pageData = computed(() => payload.value.data || {});
    const routes = computed(() => payload.value.routes || {});
    const workspace = computed(() => payload.value.workspace || {});

    return {
        payload,
        page: computed(() => payload.value.page || 'dashboard'),
        pageData,
        routes,
        pages: computed(() => routes.value.pages || {}),
        csrfToken: computed(() => payload.value.csrfToken || ''),
        flash: computed(() => payload.value.flash || {}),
        errors: computed(() => payload.value.flash?.errors || []),
        workspace,
        workspaces: computed(() => payload.value.workspaces || []),
        permissions: computed(() => workspace.value.permissions || {}),
        plan: computed(() => payload.value.plan || { name: 'Growth', features: [] }),
        user: computed(() => payload.value.user || {}),
        usage: computed(() => payload.value.usage || {}),
        navSections: computed(() => payload.value.navSections || []),
        routeGroup: (name) => routes.value[name] || {},
    };
}
