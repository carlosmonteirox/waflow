@extends('layouts.auth')

@section('title', 'Verifique seu email')

@section('content')
    @php($authPayload = [
        'page' => 'check-email',
        'title' => 'Verifique seu email',
        'data' => ['email' => $email],
    ])
    @include('auth.app')
@endsection
