@php
    $payload = $appPayload;
    $payload['flash'] = [
        'status' => session('status'),
        'apiToken' => session('created_api_token'),
        'webhookSecret' => session('created_webhook_secret'),
        'portalToken' => session('portal_verification_token'),
        'errors' => $errors->all(),
    ];
@endphp

<div id="waflow-panel"></div>
<script type="application/json" id="waflow-page-props">@json($payload)</script>
