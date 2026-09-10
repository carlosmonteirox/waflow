@extends('layouts.panel')

@section('content')
    @include('panel.partials.vue-page', ['component' => $component])
@endsection
