<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    tabs: { type: Array, required: true },
    label: { type: String, default: 'Seções da página' },
    syncHash: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);
const componentId = useId().replace(/:/g, '');
const tabButtons = ref({});

function tabId(id) {
    return `${componentId}-${id}-tab`;
}

function panelId(id) {
    return `${componentId}-${id}-panel`;
}

function setButtonRef(id, element) {
    if (element) tabButtons.value[id] = element;
}

function activate(id, focus = false) {
    if (!props.tabs.some((tab) => tab.id === id)) return;

    emit('update:modelValue', id);
    if (props.syncHash && window.location.hash !== `#${id}`) {
        window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${id}`);
    }

    if (focus) nextTick(() => tabButtons.value[id]?.focus());
}

function handleKeydown(event, index) {
    const lastIndex = props.tabs.length - 1;
    let nextIndex = index;

    if (event.key === 'ArrowRight') nextIndex = index === lastIndex ? 0 : index + 1;
    else if (event.key === 'ArrowLeft') nextIndex = index === 0 ? lastIndex : index - 1;
    else if (event.key === 'Home') nextIndex = 0;
    else if (event.key === 'End') nextIndex = lastIndex;
    else return;

    event.preventDefault();
    activate(props.tabs[nextIndex].id, true);
}

function activateHashTab() {
    if (!props.syncHash) return;
    const hashId = window.location.hash.slice(1);
    if (props.tabs.some((tab) => tab.id === hashId)) activate(hashId);
}

onMounted(() => {
    activateHashTab();
    window.addEventListener('hashchange', activateHashTab);
});

onBeforeUnmount(() => {
    window.removeEventListener('hashchange', activateHashTab);
});
</script>

<template>
    <div class="waflow-tabs">
        <div class="waflow-tab-list" role="tablist" :aria-label="label">
            <button
                v-for="(tab, index) in tabs"
                :id="tabId(tab.id)"
                :key="tab.id"
                :ref="(element) => setButtonRef(tab.id, element)"
                class="waflow-tab-button"
                :class="{ 'is-active': modelValue === tab.id }"
                type="button"
                role="tab"
                :aria-controls="panelId(tab.id)"
                :aria-selected="modelValue === tab.id"
                :tabindex="modelValue === tab.id ? 0 : -1"
                @click="activate(tab.id)"
                @keydown="handleKeydown($event, index)"
            >
                <i v-if="tab.icon" class="ki-filled" :class="tab.icon"></i>
                <span>{{ tab.label }}</span>
                <span v-if="tab.count !== undefined" class="waflow-tab-count">{{ tab.count }}</span>
            </button>
        </div>
        <section :id="panelId(modelValue)" class="waflow-tab-panel" role="tabpanel" :aria-labelledby="tabId(modelValue)" tabindex="0">
            <slot :name="modelValue"></slot>
        </section>
    </div>
</template>
