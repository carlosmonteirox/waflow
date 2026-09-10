<script setup>
defineProps({
    action: { type: String, required: true },
    query: { type: String, default: '' },
    status: { type: String, default: '' },
    statuses: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Buscar nesta lista' },
});
</script>

<template>
    <form class="waflow-table-toolbar" :action="action" method="get" role="search">
        <label class="waflow-table-search kt-input kt-input-sm">
            <span class="sr-only">{{ placeholder }}</span>
            <i class="ki-filled ki-magnifier text-muted-foreground"></i>
            <input name="q" :value="query" type="search" :placeholder="placeholder">
        </label>
        <select v-if="statuses.length" class="kt-input kt-input-sm waflow-table-filter" name="status" aria-label="Filtrar por status" :value="status">
            <option value="">Todos os status</option>
            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <button class="kt-btn kt-btn-sm kt-btn-outline" type="submit">Filtrar</button>
        <a v-if="query || status" class="kt-btn kt-btn-sm kt-btn-ghost" :href="action">Limpar</a>
    </form>
</template>
