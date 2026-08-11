@php
    $payload = array_replace_recursive([
        'page' => 'login',
        'title' => 'Login',
        'csrfToken' => csrf_token(),
        'routes' => [
            'login' => route('login'),
            'loginStore' => route('login.store'),
            'register' => route('register'),
            'registerStore' => route('register.store'),
            'passwordRequest' => route('password.request'),
            'passwordEmail' => route('password.email'),
            'passwordStore' => route('password.store'),
            'twoFactorStore' => route('two-factor.store'),
            'twoFactorResend' => route('two-factor.resend'),
        ],
        'old' => [
            'name' => old('name'),
            'workspace_name' => old('workspace_name'),
            'email' => old('email'),
        ],
        'flash' => [
            'status' => session('status'),
            'errors' => $errors->all(),
        ],
        'data' => [],
    ], $authPayload ?? []);
@endphp

<div id="waflow-auth"></div>
<script type="application/json" id="waflow-page-props">@json($payload)</script>
