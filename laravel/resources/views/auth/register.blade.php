@extends('layouts.auth')

@section('title', 'Criar conta')

@section('content')
    @php($authPayload = [
        'page' => 'register',
        'title' => 'Criar conta',
        'data' => [
            'partner' => $partner ? [
                'name' => $partner->brand_name ?: $partner->name,
                'code' => $partner->referral_code,
            ] : null,
        ],
    ])
    @include('auth.app')
@endsection
