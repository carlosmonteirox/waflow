@extends('layouts.panel')

@section('content')
    @php
        $payload = $appPayload;
        $payload['flash'] = [
            'status' => session('status'),
            'apiToken' => session('created_api_token'),
            'webhookSecret' => session('created_webhook_secret'),
            'errors' => $errors->all(),
        ];
    @endphp

    <div id="waflow-panel"></div>
    <script type="application/json" id="waflow-page-props">@json($payload)</script>
@endsection
