<script setup>
defineProps({
    pagination: { type: Object, required: true },
    label: { type: String, default: 'itens' },
});
</script>

<template>
    <div v-if="pagination.total" class="waflow-pagination">
        <p>Exibindo {{ pagination.from }}–{{ pagination.to }} de {{ pagination.total }} {{ label }}</p>
        <nav v-if="pagination.links?.length" aria-label="Paginação">
            <template v-for="link in pagination.links" :key="`${link.label}-${link.url}`">
                <a v-if="link.url" class="kt-btn kt-btn-sm" :class="link.active ? 'kt-btn-primary' : 'kt-btn-outline'" :href="link.url" :aria-current="link.active ? 'page' : undefined">{{ link.label }}</a>
                <span v-else class="kt-btn kt-btn-sm kt-btn-outline" aria-disabled="true">{{ link.label }}</span>
            </template>
        </nav>
    </div>
</template>
