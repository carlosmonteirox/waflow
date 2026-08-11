@extends('layouts.auth')

@section('title', 'Criar conta')

@section('content')
    @php($authPayload = ['page' => 'register', 'title' => 'Criar conta'])
    @include('auth.app')
@endsection
