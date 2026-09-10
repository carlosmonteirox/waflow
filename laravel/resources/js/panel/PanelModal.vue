<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const emit = defineEmits(['update:modelValue']);
const dialog = ref(null);
const titleId = `${useId()}-title`;
const descriptionId = `${titleId}-description`;
let previousFocus = null;

function close() {
    emit('update:modelValue', false);
}

function focusableElements() {
    return Array.from(dialog.value?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ) || []).filter((element) => !element.hasAttribute('hidden'));
}

function handleKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key !== 'Tab') return;

    const elements = focusableElements();
    if (!elements.length) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = elements[0];
    const last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.modelValue,
    async (open) => {
        if (open) {
            previousFocus = document.activeElement;
            document.body.classList.add('waflow-modal-open');
            await nextTick();
            const preferredTarget = dialog.value?.querySelector('[data-modal-autofocus]');
            (preferredTarget || focusableElements()[0] || dialog.value)?.focus();
            return;
        }

        document.body.classList.remove('waflow-modal-open');
        await nextTick();
        previousFocus?.focus?.();
        previousFocus = null;
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    document.body.classList.remove('waflow-modal-open');
});
</script>

<template>
    <Teleport to="body">
        <Transition name="waflow-modal">
            <div v-if="modelValue" class="waflow-modal-backdrop" role="presentation" @mousedown.self="close">
                <section
                    ref="dialog"
                    class="waflow-modal-dialog"
                    :class="`waflow-modal-${size}`"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :aria-describedby="description ? descriptionId : undefined"
                    tabindex="-1"
                    @keydown="handleKeydown"
                >
                    <header class="waflow-modal-header">
                        <div>
                            <h2 :id="titleId">{{ title }}</h2>
                            <p v-if="description" :id="descriptionId">{{ description }}</p>
                        </div>
                        <button class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" type="button" aria-label="Fechar modal" @click="close">
                            <i class="ki-filled ki-cross"></i>
                        </button>
                    </header>
                    <div class="waflow-modal-body">
                        <slot :close="close"></slot>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
