<script setup>
defineProps({
    icon: { type: String, default: 'ki-information-2' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    actionHref: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
    actionIcon: { type: String, default: '' },
    actionButton: { type: Boolean, default: false },
    resettable: { type: Boolean, default: false },
});

defineEmits(['action', 'reset']);
</script>

<template>
    <div class="waflow-empty-state">
        <span class="waflow-empty-state-icon"><i class="ki-filled" :class="icon"></i></span>
        <div class="font-medium text-foreground">{{ title }}</div>
        <p v-if="description">{{ description }}</p>
        <div v-if="actionHref || actionButton || resettable" class="flex flex-wrap justify-center gap-2">
            <a v-if="actionHref" class="kt-btn kt-btn-sm kt-btn-primary" :href="actionHref"><i v-if="actionIcon" class="ki-filled" :class="actionIcon"></i>{{ actionLabel }}</a>
            <button v-else-if="actionButton" class="kt-btn kt-btn-sm kt-btn-primary" type="button" @click="$emit('action')"><i v-if="actionIcon" class="ki-filled" :class="actionIcon"></i>{{ actionLabel }}</button>
            <button v-if="resettable" class="kt-btn kt-btn-sm kt-btn-outline" type="button" @click="$emit('reset')">Limpar filtros</button>
        </div>
    </div>
</template>
