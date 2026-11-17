<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'CentralCorp Panel'))</title>

    @php
        $assetVersion = rawurlencode(\App\Support\PanelVersion::current());
    @endphp

    <script>
        (function() {
            document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('theme') || 'light');
        })();
    </script>

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/vendor/admin.css') }}?v={{ $assetVersion }}">
    <link id="file-manager-css" rel="stylesheet" href="{{ asset('vendor/file-manager/css/file-manager.css') }}?v={{ $assetVersion }}">
    <link id="file-manager-dark-css" rel="stylesheet" href="{{ asset('vendor/file-manager/css/file-manager-dark.css') }}?v={{ $assetVersion }}" disabled>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ $assetVersion }}">
    @stack('styles')
</head>

@php
    $navGroups = [
        __('messages.sidebar.panel') => [
            ['route' => 'admin.index', 'icon' => 'bi-speedometer2', 'label' => __('messages.dashboard.title'), 'match' => 'admin.index'],
            ['route' => 'admin.users', 'icon' => 'bi-people', 'label' => __('messages.sidebar.users'), 'match' => 'admin.users*'],
            ['route' => 'admin.config', 'icon' => 'bi-gear', 'label' => __('messages.sidebar.config'), 'match' => 'admin.config*'],
            ['route' => 'admin.file-manager', 'icon' => 'bi-folder', 'label' => __('messages.sidebar.file_manager'), 'match' => 'admin.file-manager'],
            ['route' => 'admin.update', 'icon' => 'bi-cloud-arrow-up', 'label' => __('messages.sidebar.update'), 'match' => 'admin.update'],
        ],
        __('messages.sidebar.configuration') => [
            ['route' => 'admin.general', 'icon' => 'bi-sliders', 'label' => __('messages.sidebar.general'), 'match' => 'admin.general*'],
            ['route' => 'admin.rpc', 'icon' => 'bi-discord', 'label' => __('messages.sidebar.rpc'), 'match' => 'admin.rpc*'],
            ['route' => 'admin.server', 'icon' => 'bi-hdd-network', 'label' => __('messages.sidebar.server'), 'match' => 'admin.server*'],
        ],
        __('messages.sidebar.security_header') => [
            ['route' => 'admin.security', 'icon' => 'bi-shield-lock', 'label' => __('messages.sidebar.security_link'), 'match' => 'admin.security*'],
            ['route' => 'admin.whitelist', 'icon' => 'bi-list-check', 'label' => __('messages.sidebar.whitelist'), 'match' => 'admin.whitelist*'],
            ['route' => 'admin.roles', 'icon' => 'bi-person-badge', 'label' => __('messages.sidebar.roles'), 'match' => 'admin.roles*'],
            ['route' => 'admin.players', 'icon' => 'bi-controller', 'label' => __('messages.sidebar.players'), 'match' => 'admin.players*'],
        ],
        __('messages.sidebar.client') => [
            ['route' => 'admin.instances', 'icon' => 'bi-layers', 'label' => __('messages.sidebar.instances'), 'match' => 'admin.instances*'],
            ['route' => 'admin.mods', 'icon' => 'bi-box-seam', 'label' => __('messages.sidebar.optional_mods'), 'match' => 'admin.mods*'],
            ['route' => 'admin.loader', 'icon' => 'bi-cloud-arrow-down', 'label' => __('messages.sidebar.loader'), 'match' => 'admin.loader*'],
            ['route' => 'admin.ignore', 'icon' => 'bi-slash-circle', 'label' => __('messages.sidebar.ignore'), 'match' => 'admin.ignore*'],
        ],
        __('messages.sidebar.interface') => [
            ['route' => 'admin.ui', 'icon' => 'bi-window-sidebar', 'label' => __('messages.sidebar.ui'), 'match' => 'admin.ui*'],
            ['route' => 'admin.bg', 'icon' => 'bi-image', 'label' => __('messages.sidebar.background'), 'match' => 'admin.bg*'],
            ['route' => 'admin.news', 'icon' => 'bi-newspaper', 'label' => __('messages.sidebar.news'), 'match' => 'admin.news*'],
        ],
    ];

    $navInstances = \App\Support\ActiveInstance::all();
    $currentActiveInstance = $navInstances->isNotEmpty() ? \App\Support\ActiveInstance::current() : null;
@endphp

<body>
<div class="wrapper">
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content js-simplebar">
            <a class="sidebar-brand text-white" href="{{ route('admin.index') }}" aria-label="{{ config('app.name', 'CentralCorp Panel') }}">
                <img src="{{ asset('assets/img/logo.png') }}?v={{ $assetVersion }}" alt="{{ config('app.name', 'CentralCorp Panel') }}" class="sidebar-brand-img">
            </a>

            <ul class="sidebar-nav">
                @foreach($navGroups as $header => $items)
                    <li class="sidebar-header">{{ $header }}</li>
                    @foreach($items as $item)
                        @continue(($item['optional'] ?? false) && !Route::has($item['route']))
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs($item['match']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <i class="bi {{ $item['icon'] }} align-middle"></i>
                                <span class="align-middle">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </nav>

    <div class="main">
        <nav class="navbar navbar-expand navbar-bgw bg-body">
            <button class="sidebar-toggle js-sidebar-toggle btn btn-link text-body" type="button" aria-label="Menu">
                <i class="hamburger align-self-center"></i>
            </button>

            <div class="d-none d-md-flex align-items-center gap-2">
                <a href="https://discord.gg/VCmNXHvf77" class="btn btn-outline-primary btn-sm btn-icon" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-discord"></i>
                    {{ __('messages.navbar.discord') }}
                </a>
                <a href="https://centralcorp.github.io/" class="btn btn-outline-secondary btn-sm btn-icon" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-journals"></i>
                    {{ __('messages.navbar.documentation') }}
                </a>
            </div>

            <div class="ms-auto d-flex align-items-center gap-2">
                @if($currentActiveInstance)
                    <div class="dropdown">
                        <button class="btn btn-outline-primary btn-sm dropdown-toggle btn-icon" type="button" id="instanceSwitcherDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-layers"></i>
                            <span class="d-none d-sm-inline">{{ __('messages.sidebar.active_instance') }} :</span>
                            <strong>{{ $currentActiveInstance->name }}</strong>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="instanceSwitcherDropdown">
                            @foreach($navInstances as $inst)
                                <li>
                                    <form action="{{ route('admin.instances.switch') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="instance_id" value="{{ $inst->id }}">
                                        <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center gap-3 {{ $inst->id === $currentActiveInstance->id ? 'active' : '' }}">
                                            <span>
                                                <i class="bi {{ $inst->id === $currentActiveInstance->id ? 'bi-check2-circle' : 'bi-box' }} me-2"></i>
                                                {{ $inst->name }}
                                            </span>
                                            <span class="small opacity-75">{{ $inst->minecraft_version }}</span>
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.instances') }}">
                                    <i class="bi bi-gear me-2"></i>{{ __('messages.sidebar.manage_instances') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif

                <button class="btn btn-outline-secondary btn-square"
                        id="themeToggle"
                        type="button"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        data-theme-dark-label="{{ __('messages.navbar.theme_dark') }}"
                        data-theme-light-label="{{ __('messages.navbar.theme_light') }}"
                        aria-label="{{ __('messages.navbar.theme_dark') }}">
                    <i id="themeIcon" class="bi bi-moon-stars"></i>
                </button>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle btn-icon" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate"></i>
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                        <li><a class="dropdown-item" href="{{ route('lang.switch', 'fr') }}">Français</a></li>
                        <li><a class="dropdown-item" href="{{ route('lang.switch', 'en') }}">English</a></li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ Auth::user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="bi bi-box-arrow-right me-2"></i>{{ __('messages.navbar.logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="content">
            <div class="container-fluid">
                @if(View::hasSection('page-title'))
                    <x-admin.page-header :title="trim($__env->yieldContent('page-title'))" />
                @endif

                <x-admin.flash />
                @yield('content')
            </div>
        </main>

        <footer class="bg-body border-top py-3 mt-auto">
            <div class="container-fluid text-center text-body-secondary small">
                {{ __('messages.dashboard.copy_rights') }}
            </div>
        </footer>
    </div>
</div>

<script src="{{ asset('assets/vendor/admin.js') }}?v={{ $assetVersion }}"></script>
<script src="{{ asset('vendor/file-manager/js/file-manager.js') }}?v={{ $assetVersion }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('assets/js/panel.js') }}?v={{ $assetVersion }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
