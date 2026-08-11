import './bootstrap';
import { createApp } from 'vue';
import AuthApp from './components/AuthApp.vue';
import PanelApp from './components/PanelApp.vue';

const payloadElement = document.getElementById('waflow-page-props');

function readPayload() {
    if (!payloadElement) {
        return {};
    }

    try {
        return JSON.parse(payloadElement.textContent || '{}');
    } catch (error) {
        console.error('Invalid Waflow page payload.', error);

        return {};
    }
}

function mount(selector, component) {
    const element = document.querySelector(selector);

    if (!element) {
        return;
    }

    createApp(component, {
        payload: readPayload(),
    }).mount(element);
}

mount('#waflow-auth', AuthApp);
mount('#waflow-panel', PanelApp);
