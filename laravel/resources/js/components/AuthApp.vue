<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    payload: {
        type: Object,
        required: true,
    },
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);
const isDarkMode = ref(false);
const loginEmail = ref(props.payload.old?.email || '');
const loginPassword = ref('');
const verificationDigits = ref(Array.from({ length: 6 }, () => ''));
const verificationInputs = ref([]);

const page = computed(() => props.payload.page || 'login');
const csrfToken = computed(() => props.payload.csrfToken || '');
const routes = computed(() => props.payload.routes || {});
const old = computed(() => props.payload.old || {});
const data = computed(() => props.payload.data || {});
const flash = computed(() => props.payload.flash || {});
const errors = computed(() => flash.value.errors || []);

function route(name) {
    return routes.value[name] || '#';
}

function oldValue(name, fallback = '') {
    return old.value[name] ?? fallback;
}

function setVerificationInput(element, index) {
    if (element) {
        verificationInputs.value[index] = element;
    }
}

function verificationNumbers(value) {
    return `${value || ''}`.replace(/\D/g, '').slice(0, 6);
}

async function focusVerificationInput(index) {
    await nextTick();
    const input = verificationInputs.value[Math.max(0, Math.min(5, index))];
    input?.focus();
    input?.select();
}

function distributeVerificationCode(value, startIndex = 0) {
    const numbers = verificationNumbers(value);
    if (!numbers) return;

    numbers.split('').forEach((number, offset) => {
        const index = startIndex + offset;
        if (index < verificationDigits.value.length) {
            verificationDigits.value[index] = number;
        }
    });

    const nextEmpty = verificationDigits.value.findIndex((number, index) => index >= startIndex && !number);
    focusVerificationInput(nextEmpty === -1 ? 5 : nextEmpty);
}

function handleVerificationInput(event, index) {
    const numbers = verificationNumbers(event.target.value);

    if (numbers.length > 1) {
        distributeVerificationCode(numbers, index);
        return;
    }

    verificationDigits.value[index] = numbers;
    event.target.value = numbers;
    if (numbers && index < 5) {
        focusVerificationInput(index + 1);
    }
}

function handleVerificationPaste(event, index) {
    const numbers = verificationNumbers(event.clipboardData?.getData('text'));
    if (!numbers) return;

    event.preventDefault();
    distributeVerificationCode(numbers, numbers.length === 6 ? 0 : index);
}

function handleVerificationKeydown(event, index) {
    if (event.key === 'Backspace') {
        event.preventDefault();
        if (verificationDigits.value[index]) {
            verificationDigits.value[index] = '';
            focusVerificationInput(index);
        } else if (index > 0) {
            verificationDigits.value[index - 1] = '';
            focusVerificationInput(index - 1);
        }
    } else if (event.key === 'Delete') {
        event.preventDefault();
        verificationDigits.value[index] = '';
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusVerificationInput(index - 1);
    } else if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        focusVerificationInput(index + 1);
    }
}

function toggleTheme() {
    isDarkMode.value = !isDarkMode.value;
    const theme = isDarkMode.value ? 'dark' : 'light';

    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.classList.add(theme);
    document.documentElement.dataset.ktThemeMode = theme;
    document.documentElement.style.colorScheme = theme;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isDarkMode.value ? '#09090b' : '#ffffff');
    localStorage.setItem('kt-theme', theme);
}

onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
});
</script>

<template>
    <button class="waflow-auth-theme-toggle kt-btn kt-btn-icon kt-btn-outline" type="button" :aria-label="isDarkMode ? 'Usar tema claro' : 'Usar tema escuro'" :title="isDarkMode ? 'Usar tema claro' : 'Usar tema escuro'" @click="toggleTheme">
        <i class="ki-filled" :class="isDarkMode ? 'ki-sun' : 'ki-moon'"></i>
    </button>

    <div class="flex items-center justify-center grow w-full px-4 py-8">
        <div v-if="page === 'login'" class="kt-card waflow-auth-card-sm">
            <form :action="route('loginStore')" class="kt-card-content flex flex-col gap-5 p-10 waflow-auth-padding" method="post">
                <input name="_token" type="hidden" :value="csrfToken">

                <div class="text-center mb-2.5">
                    <div class="flex justify-center mb-5">
                        <span class="inline-flex items-center justify-center size-12 rounded-xl bg-primary text-primary-foreground">
                            <i class="ki-filled ki-whatsapp text-2xl"></i>
                        </span>
                    </div>
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Entrar no Waflow</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Ainda não tem conta?</span>
                        <a class="text-sm link" :href="route('register')">Criar conta</a>
                    </div>
                </div>

                <div v-if="flash.status" class="kt-alert kt-alert-light kt-alert-success text-sm" role="status">
                    {{ flash.status }}
                </div>
                <div v-if="errors.length" class="kt-alert kt-alert-light kt-alert-destructive text-sm" role="alert">
                    {{ errors[0] }}
                </div>

                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono" for="email">Email</label>
                    <input id="email" v-model="loginEmail" class="kt-input" name="email" placeholder="email@empresa.com" type="email" autocomplete="email" required autofocus>
                </div>

                <div class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-1">
                        <label class="kt-form-label font-normal text-mono" for="password">Senha</label>
                        <a class="text-sm kt-link shrink-0" :href="route('passwordRequest')">Esqueci minha senha</a>
                    </div>
                    <div class="kt-input">
                            <input id="password" v-model="loginPassword" name="password" placeholder="Digite sua senha" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required>
                        <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" type="button" aria-label="Alternar senha" @click="showPassword = !showPassword">
                            <i class="ki-filled text-muted-foreground" :class="showPassword ? 'ki-eye-slash' : 'ki-eye'"></i>
                        </button>
                    </div>
                </div>

                <label class="kt-label">
                    <input class="kt-checkbox kt-checkbox-sm" name="remember" type="checkbox" value="1">
                    <span class="kt-checkbox-label">Manter conectado</span>
                </label>

                <button class="kt-btn kt-btn-primary flex justify-center grow" type="submit">Entrar</button>
            </form>
        </div>

        <div v-else-if="page === 'register'" class="kt-card waflow-auth-card-md">
            <form :action="route('registerStore')" class="kt-card-content flex flex-col gap-5 p-10 waflow-auth-padding" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <input v-if="data.partner?.code" name="partner_code" type="hidden" :value="data.partner.code">

                <div class="text-center mb-2.5">
                    <div class="flex justify-center mb-5">
                        <span class="inline-flex items-center justify-center size-12 rounded-xl bg-primary text-primary-foreground">
                            <i class="ki-filled ki-whatsapp text-2xl"></i>
                        </span>
                    </div>
                    <h3 class="text-lg font-medium text-mono leading-none mb-2.5">Criar conta Waflow</h3>
                    <div class="flex items-center justify-center font-medium">
                        <span class="text-sm text-secondary-foreground me-1.5">Já tem conta?</span>
                        <a class="text-sm link" :href="route('login')">Entrar</a>
                    </div>
                </div>

                <div v-if="errors.length" class="kt-alert kt-alert-light kt-alert-destructive text-sm" role="alert">
                    {{ errors[0] }}
                </div>
                <div v-if="data.partner" class="kt-alert kt-alert-light kt-alert-primary text-sm">
                    Cadastro indicado por <strong>{{ data.partner.name }}</strong>. Sua empresa ficará vinculada a este parceiro comercial.
                </div>

                <div class="grid gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono" for="name">Nome</label>
                        <input id="name" class="kt-input" name="name" placeholder="Seu nome" type="text" :value="oldValue('name')" required autofocus>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono" for="workspace_name">Workspace</label>
                        <input id="workspace_name" class="kt-input" name="workspace_name" placeholder="Nome da empresa" type="text" :value="oldValue('workspace_name')">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono" for="email">Email</label>
                        <input id="email" class="kt-input" name="email" placeholder="email@empresa.com" type="email" :value="oldValue('email')" required>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono" for="password">Senha</label>
                        <div class="kt-input">
                            <input id="password" name="password" placeholder="Mínimo seguro" :type="showPassword ? 'text' : 'password'" required>
                            <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" type="button" aria-label="Alternar senha" @click="showPassword = !showPassword">
                                <i class="ki-filled text-muted-foreground" :class="showPassword ? 'ki-eye-slash' : 'ki-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="kt-form-label font-normal text-mono" for="password_confirmation">Confirmar senha</label>
                        <div class="kt-input">
                            <input id="password_confirmation" name="password_confirmation" placeholder="Repita a senha" :type="showPasswordConfirmation ? 'text' : 'password'" required>
                            <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" type="button" aria-label="Alternar confirmação" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                <i class="ki-filled text-muted-foreground" :class="showPasswordConfirmation ? 'ki-eye-slash' : 'ki-eye'"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button class="kt-btn kt-btn-primary flex justify-center grow" type="submit">Criar workspace</button>
            </form>
        </div>

        <div v-else-if="page === 'forgot-password'" class="kt-card waflow-auth-card-sm">
            <form :action="route('passwordEmail')" class="kt-card-content flex flex-col gap-5 p-10 waflow-auth-padding" method="post">
                <input name="_token" type="hidden" :value="csrfToken">

                <div class="text-center mb-2">
                    <h3 class="text-lg font-medium text-mono mb-3">Recuperar senha</h3>
                    <p class="text-sm text-secondary-foreground">Informe o email da conta para receber o link de redefinição.</p>
                </div>

                <div v-if="errors.length" class="kt-alert kt-alert-light kt-alert-destructive text-sm" role="alert">
                    {{ errors[0] }}
                </div>

                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono" for="email">Email</label>
                    <input id="email" class="kt-input" name="email" placeholder="email@empresa.com" type="email" :value="oldValue('email')" required autofocus>
                </div>

                <button class="kt-btn kt-btn-primary flex justify-center grow" type="submit">Enviar link</button>

                <div class="flex justify-center">
                    <a class="text-sm kt-link" :href="route('login')">Voltar para login</a>
                </div>
            </form>
        </div>

        <div v-else-if="page === 'check-email'" class="kt-card waflow-auth-card-md">
            <div class="kt-card-content p-10 waflow-auth-padding">
                <div class="flex justify-center py-10">
                    <img alt="" class="dark:hidden max-h-[130px]" :src="'/assets/media/illustrations/30.svg'">
                    <img alt="" class="light:hidden max-h-[130px]" :src="'/assets/media/illustrations/30-dark.svg'">
                </div>
                <h3 class="text-lg font-medium text-mono text-center mb-3">Verifique seu email</h3>
                <div class="text-sm text-center text-secondary-foreground mb-7.5">
                    Enviamos as instruções para
                    <span class="text-sm text-mono font-medium">{{ data.email || 'o email informado' }}</span>.
                </div>
                <div class="flex justify-center mb-5">
                    <a class="kt-btn kt-btn-primary flex justify-center" :href="route('login')">Voltar para login</a>
                </div>
                <div class="flex items-center justify-center gap-1 text-2sm">
                    <span class="text-secondary-foreground">Não recebeu?</span>
                    <a class="font-medium kt-link" :href="route('passwordRequest')">Enviar novamente</a>
                </div>
            </div>
        </div>

        <div v-else-if="page === 'reset-password'" class="kt-card waflow-auth-card-sm">
            <form :action="route('passwordStore')" class="kt-card-content flex flex-col gap-5 p-10 waflow-auth-padding" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <input name="token" type="hidden" :value="data.token">

                <div class="text-center mb-2">
                    <h3 class="text-lg font-medium text-mono mb-3">Definir nova senha</h3>
                    <p class="text-sm text-secondary-foreground">Crie uma senha nova para recuperar o acesso ao painel.</p>
                </div>

                <div v-if="errors.length" class="kt-alert kt-alert-light kt-alert-destructive text-sm" role="alert">
                    {{ errors[0] }}
                </div>

                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono" for="email">Email</label>
                    <input id="email" class="kt-input" name="email" placeholder="email@empresa.com" type="email" :value="oldValue('email', data.email)" required autofocus>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono" for="password">Nova senha</label>
                    <input id="password" class="kt-input" name="password" placeholder="Digite a nova senha" type="password" required>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="kt-form-label font-normal text-mono" for="password_confirmation">Confirmar senha</label>
                    <input id="password_confirmation" class="kt-input" name="password_confirmation" placeholder="Repita a nova senha" type="password" required>
                </div>

                <button class="kt-btn kt-btn-primary flex justify-center grow" type="submit">Salvar senha</button>
            </form>
        </div>

        <div v-else-if="page === 'two-factor'" class="kt-card waflow-auth-card-sm">
            <form :action="route('twoFactorStore')" class="kt-card-content flex flex-col gap-5 p-10 waflow-auth-padding" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <!-- <img alt="" class="dark:hidden h-20 mb-2" :src="'/assets/media/illustrations/34.svg'"> -->
                <!-- <img alt="" class="light:hidden h-20 mb-2" :src="'/assets/media/illustrations/34-dark.svg'"> -->
                <div class="text-center mb-2">
                    <h3 class="text-lg font-medium text-mono mb-5">Verificação em duas etapas</h3>
                    <div class="flex flex-col">
                        <span class="text-sm text-secondary-foreground mb-1.5">Enviamos um código de 6 números para o e-mail cadastrado.</span>
                        <span class="text-sm font-medium text-mono">O código expira em 5 minutos.</span>
                    </div>
                </div>
                <input name="verification_code" type="hidden" :value="verificationDigits.join('')">
                <div id="verification-code-label" class="sr-only">Código de verificação de 6 números</div>
                <div class="waflow-verification-code" role="group" aria-labelledby="verification-code-label">
                    <input
                        v-for="(_, index) in verificationDigits"
                        :key="index"
                        :ref="(element) => setVerificationInput(element, index)"
                        :value="verificationDigits[index]"
                        :aria-label="`Dígito ${index + 1} de 6`"
                        :autocomplete="index === 0 ? 'one-time-code' : 'off'"
                        :autofocus="index === 0"
                        class="kt-input waflow-verification-code-input"
                        inputmode="numeric"
                        maxlength="6"
                        pattern="[0-9]*"
                        required
                        type="text"
                        @focus="$event.target.select()"
                        @input="handleVerificationInput($event, index)"
                        @keydown="handleVerificationKeydown($event, index)"
                        @paste="handleVerificationPaste($event, index)"
                    >
                </div>
                <div v-if="flash.status" class="kt-alert kt-alert-light kt-alert-success text-sm" role="status">{{ flash.status }}</div>
                <div v-if="errors.length" class="kt-alert kt-alert-light kt-alert-destructive text-sm" role="alert">{{ errors[0] }}</div>
                <button class="kt-btn kt-btn-primary flex justify-center grow" type="submit">Continuar</button>
            </form>
            <form :action="route('twoFactorResend')" class="px-10 pb-8 -mt-5" method="post">
                <input name="_token" type="hidden" :value="csrfToken">
                <button class="kt-btn kt-btn-ghost w-full" type="submit">Reenviar código</button>
            </form>
        </div>
    </div>
</template>
