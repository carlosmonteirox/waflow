@extends('layouts.auth')

@section('title', '2FA')

@section('content')
    @php($authPayload = ['page' => 'two-factor', 'title' => '2FA'])
    @include('auth.app')
@endsection
