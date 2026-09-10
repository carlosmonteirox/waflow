<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    status: { type: String, default: 'all' },
    statuses: { type: Array, default: () => [] },
    count: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    placeholder: { type: String, default: 'Buscar nesta lista' },
});

defineEmits(['update:modelValue', 'update:status', 'reset']);
</script>

<template>
    <div class="waflow-table-toolbar">
        <label class="waflow-table-search kt-input kt-input-sm">
            <span class="sr-only">{{ placeholder }}</span>
            <i class="ki-filled ki-magnifier text-muted-foreground"></i>
            <input :value="modelValue" type="search" :placeholder="placeholder" @input="$emit('update:modelValue', $event.target.value)">
        </label>
        <select v-if="statuses.length" class="kt-input kt-input-sm waflow-table-filter" aria-label="Filtrar por status" :value="status" @change="$emit('update:status', $event.target.value)">
            <option value="all">Todos os status</option>
            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <span class="waflow-table-count" aria-live="polite">{{ count }} de {{ total }}</span>
        <button v-if="modelValue || status !== 'all'" class="kt-btn kt-btn-sm kt-btn-ghost" type="button" @click="$emit('reset')">Limpar</button>
    </div>
</template>
