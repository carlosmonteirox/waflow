@extends('layouts.panel')

@section('content')
    @include('panel.partials.vue-page', ['component' => $appPayload['page'] ?? 'dashboard'])
@endsection
