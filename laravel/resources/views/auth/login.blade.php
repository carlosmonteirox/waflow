@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    @php($authPayload = ['page' => 'login', 'title' => 'Login'])
    @include('auth.app')
@endsection
