@extends('layouts.auth')

@section('title', 'Recuperar senha')

@section('content')
    @php($authPayload = ['page' => 'forgot-password', 'title' => 'Recuperar senha'])
    @include('auth.app')
@endsection
