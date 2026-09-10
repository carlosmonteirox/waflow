<!DOCTYPE html>
<html class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr" lang="pt-BR">
<head>
    <title>{{ $title ?? 'Painel' }} | Waflow</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('assets/media/app/apple-touch-icon.png') }}" rel="apple-touch-icon" sizes="180x180">
    <link href="{{ asset('assets/media/app/favicon-32x32.png') }}" rel="icon" sizes="32x32" type="image/png">
    <link href="{{ asset('assets/media/app/favicon-16x16.png') }}" rel="icon" sizes="16x16" type="image/png">
    <link href="{{ asset('assets/media/app/favicon.ico') }}" rel="shortcut icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/vendors/apexcharts/apexcharts.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet">
    @vite(['resources/css/styles.css', 'resources/js/app.js'])
</head>
<body class="antialiased flex h-full text-base text-foreground bg-background demo1 kt-sidebar-fixed kt-header-fixed">
    <script>
        const storedThemeMode = localStorage.getItem('kt-theme') || 'system';
        const themeMode = storedThemeMode === 'system'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : storedThemeMode;

        document.documentElement.classList.remove('light', 'dark');
        document.documentElement.classList.add(themeMode);
        document.documentElement.dataset.ktThemeMode = themeMode;
        document.documentElement.style.colorScheme = themeMode;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', themeMode === 'dark' ? '#09090b' : '#ffffff');
    </script>

    @yield('content')

    <script src="{{ asset('assets/js/core.bundle.js') }}"></script>
    <script src="{{ asset('assets/vendors/ktui/ktui.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>
</body>
</html>
