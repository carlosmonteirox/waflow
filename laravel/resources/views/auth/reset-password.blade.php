@extends('layouts.auth')

@section('title', 'Nova senha')

@section('content')
    @php($authPayload = [
        'page' => 'reset-password',
        'title' => 'Nova senha',
        'data' => [
            'email' => $email,
            'token' => $token,
        ],
    ])
    @include('auth.app')
@endsection
