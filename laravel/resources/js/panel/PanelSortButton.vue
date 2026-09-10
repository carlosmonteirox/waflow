<script setup>
const props = defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    activeField: { type: String, default: '' },
    direction: { type: String, default: 'asc' },
    href: { type: String, default: '' },
});

defineEmits(['sort']);

const ariaSort = () => {
    if (props.activeField !== props.field) return 'none';

    return props.direction === 'desc' ? 'descending' : 'ascending';
};
</script>

<template>
    <a v-if="href" class="waflow-sort-button" :href="href" :aria-label="`Ordenar por ${label}`" :aria-current="activeField === field ? 'true' : undefined">
        {{ label }}
        <i class="ki-filled" :class="activeField === field ? (direction === 'desc' ? 'ki-down' : 'ki-up') : 'ki-arrow-up-down'"></i>
    </a>
    <button v-else class="waflow-sort-button" type="button" :aria-label="`Ordenar por ${label}`" :aria-pressed="activeField === field" :data-sort="ariaSort()" @click="$emit('sort', field)">
        {{ label }}
        <i class="ki-filled" :class="activeField === field ? (direction === 'desc' ? 'ki-down' : 'ki-up') : 'ki-arrow-up-down'"></i>
    </button>
</template>
