@extends('layouts.admin')

@section('title', __('messages.curseforge.title'))
@section('page-title', __('messages.curseforge.page_title'))

@section('content')
<div class="container-fluid p-0" id="curseforge-manager">
    {{-- Instance Status Banner --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">{{ __('messages.curseforge.active_instance') }}</div>
                    <div class="fw-bold fs-5">
                        <i class="bi bi-layers text-primary me-1"></i>
                        {{ $activeInstance->name }}
                        <span class="badge bg-secondary ms-1">{{ $activeInstance->slug }}</span>
                    </div>
                </div>

                <div class="vr d-none d-md-block"></div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-info text-dark fs-6 fw-normal px-3 py-2">
                        <i class="bi bi-controller me-1"></i>
                        MC {{ $mcVersion ?? __('messages.curseforge.mc_undefined') }}
                    </span>

                    @if($loaderSupported)
                        <span class="badge bg-success fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-cpu me-1"></i>
                            {{ ucfirst($resolvedLoader) }}
                        </span>
                    @else
                        <span class="badge bg-warning text-dark fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ __('messages.curseforge.loader_unsupported_badge', ['loader' => ucfirst($rawLoader)]) }}
                        </span>
                    @endif

                    @if($hasApiKey)
                        <span id="cf-api-badge" class="badge bg-success-subtle text-success border border-success-subtle fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-key-fill me-1"></i> {{ __('messages.curseforge.api_key_configured') }}
                        </span>
                    @else
                        <span id="cf-api-badge" class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-key me-1"></i> {{ __('messages.curseforge.api_key_missing') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#cfApiKeyCollapse" aria-expanded="{{ $hasApiKey ? 'false' : 'true' }}">
                    <i class="bi bi-gear me-1"></i> {{ __('messages.curseforge.configure_api_key') }}
                </button>
                <a href="{{ route('admin.loader') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-sliders me-1"></i> {{ __('messages.curseforge.configure_loader') }}
                </a>
            </div>
        </div>

        {{-- Quick API Key Config Collapse --}}
        <div class="collapse {{ $hasApiKey ? '' : 'show' }} border-top" id="cfApiKeyCollapse">
            <div class="card-body bg-body-tertiary">
                <form id="cfApiKeyForm" method="POST" action="{{ route('admin.curseforge.api-key') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-8 col-lg-6">
                        <label for="curseforge_api_key" class="form-label fw-semibold mb-1">
                            <i class="bi bi-key me-1"></i> {{ __('messages.curseforge.api_key_label') }} (<code>x-api-key</code>)
                        </label>
                        <input
                            type="password"
                            name="curseforge_api_key"
                            id="curseforge_api_key"
                            class="form-control"
                            placeholder="{{ $hasApiKey ? __('messages.curseforge.api_key_placeholder_set') : __('messages.curseforge.api_key_placeholder_empty') }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i> {{ __('messages.curseforge.save_api_key') }}
                        </button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    {{ __('messages.curseforge.api_key_help') }} <code>CURSEFORGE_API_KEY</code>.
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if(!$hasApiKey)
        <div id="cf-no-key-alert" class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>{{ __('messages.curseforge.no_key_alert_title') }}</strong> {{ __('messages.curseforge.no_key_alert_body') }}
            </div>
        </div>
    @endif

    @if(!$loaderSupported)
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-shield-exclamation fs-5"></i>
            <div>
                <strong>{{ __('messages.curseforge.incompatible_loader_title') }}</strong> {{ __('messages.curseforge.incompatible_loader_body', ['loader' => $rawLoader]) }}
            </div>
        </div>
    @endif

    <div id="cf-modpack-progress" class="card shadow-sm border-primary mb-4 d-none">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold" id="cf-progress-title"></div>
                <span class="badge bg-primary" id="cf-progress-counter">0 / 0</span>
            </div>
            <div class="progress mb-2" style="height: 12px;">
                <div id="cf-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="small text-muted text-truncate" id="cf-progress-file"></div>
        </div>
    </div>

    <div id="cf-feedback-zone"></div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-tabs mb-4" id="cfTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-installed-btn" data-bs-toggle="tab" data-bs-target="#tab-installed" type="button" role="tab">
                <i class="bi bi-box-seam me-1"></i> {{ __('messages.curseforge.tab_installed') }}
                <span class="badge bg-primary ms-1" id="cf-installed-total-badge">{{ count($installed['modpacks'] ?? []) + count($installed['curseforge'] ?? []) + count($installed['external'] ?? []) }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-mods-btn" data-bs-toggle="tab" data-bs-target="#tab-mods" type="button" role="tab">
                <i class="bi bi-search me-1"></i> {{ __('messages.curseforge.tab_browse_mods') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-modpacks-btn" data-bs-toggle="tab" data-bs-target="#tab-modpacks" type="button" role="tab">
                <i class="bi bi-archive me-1"></i> {{ __('messages.curseforge.tab_modpacks') }}
            </button>
        </li>
    </ul>

    <div class="tab-content" id="cfTabContent">
        {{-- TAB 1: INSTALLED (3 SEPARATED SECTIONS: MODPACKS / INDIVIDUAL MODS / EXTERNAL) --}}
        <div class="tab-pane fade show active" id="tab-installed" role="tabpanel">
            {{-- 1. Installed Modpacks Section --}}
            <div class="card shadow-sm mb-4 border-primary-subtle">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-archive text-primary me-1"></i> {{ __('messages.curseforge.section_modpacks') }}
                        <span class="badge bg-primary ms-1" id="cf-modpacks-count">{{ count($installed['modpacks'] ?? []) }}</span>
                    </h2>
                    <button type="button" id="cf-check-updates-btn" class="btn btn-outline-primary btn-sm" {{ !$hasApiKey || !$loaderSupported ? 'disabled' : '' }}>
                        <i class="bi bi-arrow-repeat me-1"></i> {{ __('messages.curseforge.check_updates') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="cf-modpacks-list">
                        @if(empty($installed['modpacks']))
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-archive fs-2 d-block mb-2"></i>
                                {{ __('messages.curseforge.section_modpacks_empty') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 2. Individual CurseForge Mods Section --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-fire text-warning me-1"></i> {{ __('messages.curseforge.section_mods') }}
                        <span class="badge bg-secondary ms-1" id="cf-tracked-count">{{ count($installed['curseforge'] ?? []) }}</span>
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div id="cf-tracked-list">
                        @if(empty($installed['curseforge']))
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                                {{ __('messages.curseforge.section_mods_empty') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 3. External Mods Section --}}
            <div class="card shadow-sm border-warning-subtle">
                <div class="card-header bg-warning-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-0 text-body">
                            <i class="bi bi-file-earmark-zip me-1"></i> {{ __('messages.curseforge.section_external') }}
                            <span class="badge bg-warning text-dark ms-1" id="cf-external-count">{{ count($installed['external'] ?? []) }}</span>
                        </h2>
                        <small class="text-muted">{{ __('messages.curseforge.section_external_hint') }}</small>
                    </div>
                    <button type="button" id="cf-scan-external-btn" class="btn btn-warning btn-sm" {{ !$hasApiKey ? 'disabled' : '' }}>
                        <i class="bi bi-fingerprint me-1"></i> {{ __('messages.curseforge.scan_external') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="cf-external-list">
                        @if(empty($installed['external']))
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-circle fs-2 d-block mb-2"></i>
                                {{ __('messages.curseforge.section_external_empty') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- TAB 2: BROWSE MODS --}}
        <div class="tab-pane fade" id="tab-mods" role="tabpanel">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form id="cf-search-mods-form" class="row g-2 align-items-center">
                        <div class="col-12 col-md-9">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="cf-mods-query" class="form-control" placeholder="{{ __('messages.curseforge.search_mods_placeholder') }}">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-primary w-100" {{ !$hasApiKey ? 'disabled' : '' }}>
                                <i class="bi bi-search me-1"></i> {{ __('messages.common.search') }}
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-funnel me-1"></i>
                        {{ __('messages.curseforge.strict_filter_hint', [
                            'version' => $mcVersion ?? '?',
                            'loader' => $loaderSupported ? ucfirst($resolvedLoader) : __('messages.curseforge.loader_not_supported_short'),
                        ]) }}
                    </div>
                </div>
            </div>

            <div id="cf-mods-results" class="row g-3"></div>
            <div class="d-flex justify-content-between align-items-center mt-4" id="cf-mods-pagination" style="display: none !important;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-mods-prev"><i class="bi bi-chevron-left"></i> {{ __('messages.curseforge.prev') }}</button>
                <span class="text-muted small" id="cf-mods-page-info"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-mods-next">{{ __('messages.curseforge.next') }} <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        {{-- TAB 3: MODPACKS --}}
        <div class="tab-pane fade" id="tab-modpacks" role="tabpanel">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form id="cf-search-modpacks-form" class="row g-2 align-items-center">
                        <div class="col-12 col-md-9">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-archive"></i></span>
                                <input type="text" id="cf-modpacks-query" class="form-control" placeholder="{{ __('messages.curseforge.search_modpacks_placeholder') }}">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-primary w-100" {{ !$hasApiKey ? 'disabled' : '' }}>
                                <i class="bi bi-search me-1"></i> {{ __('messages.common.search') }}
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        {{ __('messages.curseforge.modpacks_info_hint') }}
                    </div>
                </div>
            </div>

            <div id="cf-modpacks-results" class="row g-3"></div>
            <div class="d-flex justify-content-between align-items-center mt-4" id="cf-modpacks-pagination" style="display: none !important;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-modpacks-prev"><i class="bi bi-chevron-left"></i> {{ __('messages.curseforge.prev') }}</button>
                <span class="text-muted small" id="cf-modpacks-page-info"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-modpacks-next">{{ __('messages.curseforge.next') }} <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $cfI18n = [
        'close' => __('messages.common.close'),
        'section_modpacks_empty' => __('messages.curseforge.section_modpacks_empty'),
        'section_mods_empty' => __('messages.curseforge.section_mods_empty'),
        'section_external_empty' => __('messages.curseforge.section_external_empty'),
        'badge_compatible' => __('messages.curseforge.badge_compatible'),
        'badge_incompatible' => __('messages.curseforge.badge_incompatible'),
        'badge_external' => __('messages.curseforge.badge_external'),
        'badge_missing_file' => __('messages.curseforge.badge_missing_file'),
        'badge_mods_count' => __('messages.curseforge.badge_mods_count'),
        'by_author' => __('messages.curseforge.by_author'),
        'no_compatible_version' => __('messages.curseforge.no_compatible_version'),
        'install_mod' => __('messages.curseforge.install_mod'),
        'install_modpack' => __('messages.curseforge.install_modpack'),
        'installing' => __('messages.curseforge.installing'),
        'installing_modpack' => __('messages.curseforge.installing_modpack'),
        'updating' => __('messages.curseforge.updating'),
        'update_btn' => __('messages.curseforge.update_btn'),
        'new_version' => __('messages.curseforge.new_version'),
        'delete_btn' => __('messages.curseforge.delete_btn'),
        'cf_match_label' => __('messages.curseforge.cf_match_label'),
        'convert_to_cf' => __('messages.curseforge.convert_to_cf'),
        'no_results' => __('messages.curseforge.no_results'),
        'loading_mods' => __('messages.curseforge.loading_mods'),
        'loading_modpacks' => __('messages.curseforge.loading_modpacks'),
        'http_error' => __('messages.curseforge.http_error'),
        'page_label' => __('messages.curseforge.page_label'),
        'updates_found' => __('messages.curseforge.updates_found'),
        'all_up_to_date' => __('messages.curseforge.all_up_to_date'),
        'external_matched' => __('messages.curseforge.external_matched'),
        'external_no_match' => __('messages.curseforge.external_no_match'),
        'confirm_install_modpack' => __('messages.curseforge.confirm_install_modpack'),
        'confirm_update_modpack' => __('messages.curseforge.confirm_update_modpack'),
        'confirm_delete_modpack' => __('messages.curseforge.confirm_delete_modpack'),
        'confirm_delete_mod' => __('messages.curseforge.confirm_delete_mod'),
        'confirm_delete_external' => __('messages.curseforge.confirm_delete_external'),
        'modpack_install_details' => __('messages.curseforge.modpack_install_details'),
        'progress_preparing' => __('messages.curseforge.progress_preparing'),
        'progress_downloading' => __('messages.curseforge.progress_downloading'),
        'progress_finalizing' => __('messages.curseforge.progress_finalizing'),
        'progress_current_file' => __('messages.curseforge.progress_current_file'),
        'unit_mb' => __('messages.curseforge.unit_mb'),
        'unit_kb' => __('messages.curseforge.unit_kb'),
        'unit_b' => __('messages.curseforge.unit_b'),
    ];
@endphp
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const I18N = @json($cfI18n);

    function t(key, params = {}) {
        let str = I18N[key] || key;
        Object.entries(params).forEach(([k, v]) => {
            str = str.replace(new RegExp(':' + k, 'g'), String(v ?? ''));
        });
        return str;
    }

    const routes = {
        installed: @json(route('admin.curseforge.installed')),
        searchMods: @json(route('admin.curseforge.search.mods')),
        searchModpacks: @json(route('admin.curseforge.search.modpacks')),
        installMod: @json(route('admin.curseforge.mods.install')),
        prepareModpack: @json(route('admin.curseforge.modpacks.prepare')),
        installModpackBatch: @json(route('admin.curseforge.modpacks.install-batch')),
        finalizeModpack: @json(route('admin.curseforge.modpacks.finalize')),
        installModpack: @json(route('admin.curseforge.modpacks.install')),
        modpacksBase: @json(url('admin/curseforge/modpacks')),
        checkUpdates: @json(route('admin.curseforge.updates.check')),
        updateModBase: @json(url('admin/curseforge/mods')),
        deleteExternal: @json(route('admin.curseforge.external.delete')),
        scanExternal: @json(route('admin.curseforge.external.scan')),
        reconcileExternal: @json(route('admin.curseforge.external.reconcile')),
    };

    const state = {
        hasApiKey: @json($hasApiKey),
        loaderSupported: @json($loaderSupported),
        installed: @json($installed),
        updates: {},
        modpackUpdates: {},
        externalMatches: {},
        modsPage: 0,
        modsLoaded: false,
        modpacksPage: 0,
        modpacksLoaded: false,
    };

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDownloads(num) {
        const n = Number(num || 0);
        if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
        return String(n);
    }

    function formatSize(bytes) {
        const b = Number(bytes || 0);
        if (b >= 1048576) return (b / 1048576).toFixed(2) + ' ' + I18N.unit_mb;
        if (b >= 1024) return (b / 1024).toFixed(1) + ' ' + I18N.unit_kb;
        return b + ' ' + I18N.unit_b;
    }

    function showFeedback(message, type = 'success') {
        const zone = document.getElementById('cf-feedback-zone');
        zone.innerHTML = `
            <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="${escapeHtml(I18N.close)}"></button>
            </div>
        `;
    }

    async function apiRequest(url, options = {}) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        }, options.headers || {});

        if (options.method && options.method !== 'GET') {
            headers['X-CSRF-TOKEN'] = csrfToken;
            if (!(options.body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }
        }

        const resp = await fetch(url, Object.assign({}, options, { headers }));
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok) {
            throw new Error(data.message || t('http_error', { status: resp.status }));
        }
        return data;
    }

    function renderInstalled() {
        const packList = document.getElementById('cf-modpacks-list');
        const cfList = document.getElementById('cf-tracked-list');
        const extList = document.getElementById('cf-external-list');
        const modpacks = state.installed.modpacks || [];
        const cfMods = state.installed.curseforge || [];
        const extMods = state.installed.external || [];

        document.getElementById('cf-modpacks-count').textContent = String(modpacks.length);
        document.getElementById('cf-tracked-count').textContent = String(cfMods.length);
        document.getElementById('cf-external-count').textContent = String(extMods.length);
        document.getElementById('cf-installed-total-badge').textContent = String(modpacks.length + cfMods.length + extMods.length);

        if (modpacks.length === 0) {
            packList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-archive fs-2 d-block mb-2"></i>
                    ${escapeHtml(I18N.section_modpacks_empty)}
                </div>
            `;
        } else {
            packList.innerHTML = `<div class="list-group list-group-flush">${modpacks.map(pack => {
                const upd = state.modpackUpdates[pack.id];
                const hasUpdate = upd && upd.has_update;
                const logoHtml = pack.logo_url
                    ? `<img src="${escapeHtml(pack.logo_url)}" alt="" class="rounded" width="48" height="48" style="object-fit: cover;">`
                    : `<div class="rounded bg-primary-subtle d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="bi bi-archive text-primary fs-5"></i></div>`;

                const updateBtn = hasUpdate
                    ? `<button type="button" class="btn btn-success btn-sm cf-update-modpack-btn" data-id="${pack.id}" data-curseforge-modpack-id="${pack.curseforge_modpack_id}" data-file-id="${upd.latest_file_id || ''}" data-name="${escapeHtml(pack.name)}">
                           <i class="bi bi-cloud-arrow-down me-1"></i> ${escapeHtml(t('update_btn', { version: upd.latest_version || I18N.new_version }))}
                       </button>`
                    : '';

                const loaderText = pack.loader ? pack.loader.charAt(0).toUpperCase() + pack.loader.slice(1) : '';
                const modsCountLabel = t('badge_mods_count', { count: pack.mods_count || 0 });

                return `
                    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            ${logoHtml}
                            <div>
                                <div class="fw-semibold">
                                    ${escapeHtml(pack.name)}
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">${escapeHtml(modsCountLabel)}</span>
                                </div>
                                <div class="small text-muted">
                                    ${escapeHtml(pack.version || pack.file_name || '')}
                                    ${pack.mc_version ? '&middot; MC ' + escapeHtml(pack.mc_version) : ''}
                                    ${loaderText ? '&middot; ' + escapeHtml(loaderText) : ''}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${updateBtn}
                            <button type="button" class="btn btn-outline-danger btn-sm cf-delete-modpack-btn" data-id="${pack.id}" data-name="${escapeHtml(pack.name)}">
                                <i class="bi bi-trash"></i> ${escapeHtml(I18N.delete_btn)}
                            </button>
                        </div>
                    </div>
                `;
            }).join('')}</div>`;
        }

        if (cfMods.length === 0) {
            cfList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                    ${escapeHtml(I18N.section_mods_empty)}
                </div>
            `;
        } else {
            cfList.innerHTML = `<div class="list-group list-group-flush">${cfMods.map(mod => {
                const upd = state.updates[mod.id];
                const hasUpdate = upd && upd.has_update;
                const logoHtml = mod.logo_url
                    ? `<img src="${escapeHtml(mod.logo_url)}" alt="" class="rounded" width="44" height="44" style="object-fit: cover;">`
                    : `<div class="rounded bg-secondary-subtle d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="bi bi-box-seam text-secondary"></i></div>`;

                const updateBtn = hasUpdate
                    ? `<button type="button" class="btn btn-success btn-sm cf-update-mod-btn" data-id="${mod.id}" data-file-id="${upd.latest_file_id || ''}">
                           <i class="bi bi-cloud-arrow-down me-1"></i> ${escapeHtml(t('update_btn', { version: upd.latest_version || I18N.new_version }))}
                       </button>`
                    : '';

                const missingBadge = mod.exists_on_disk === false
                    ? `<span class="badge bg-danger ms-1">${escapeHtml(I18N.badge_missing_file)}</span>`
                    : '';

                return `
                    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            ${logoHtml}
                            <div>
                                <div class="fw-semibold">${escapeHtml(mod.name)} ${missingBadge}</div>
                                <div class="small text-muted">
                                    <code>${escapeHtml(mod.file_name)}</code>
                                    &middot; ${escapeHtml(mod.version || '')}
                                    ${mod.size ? '&middot; ' + formatSize(mod.size) : ''}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${updateBtn}
                            <button type="button" class="btn btn-outline-danger btn-sm cf-delete-mod-btn" data-id="${mod.id}" data-name="${escapeHtml(mod.name)}">
                                <i class="bi bi-trash"></i> ${escapeHtml(I18N.delete_btn)}
                            </button>
                        </div>
                    </div>
                `;
            }).join('')}</div>`;
        }

        if (extMods.length === 0) {
            extList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-check-circle fs-2 d-block mb-2"></i>
                    ${escapeHtml(I18N.section_external_empty)}
                </div>
            `;
        } else {
            extList.innerHTML = `<div class="list-group list-group-flush">${extMods.map(ext => {
                const match = state.externalMatches[ext.file_name];
                const matchHtml = match ? `
                    <div class="mt-2 p-2 rounded border border-success bg-success-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            ${match.logo_url ? `<img src="${escapeHtml(match.logo_url)}" width="32" height="32" class="rounded" style="object-fit:cover;">` : '<i class="bi bi-check-circle-fill text-success"></i>'}
                            <div class="small">
                                <strong>${escapeHtml(I18N.cf_match_label)}</strong> ${escapeHtml(match.name)}
                                <span class="text-muted">(${escapeHtml(match.version)})</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm cf-reconcile-btn"
                            data-file="${escapeHtml(ext.file_name)}"
                            data-mod-id="${match.curseforge_mod_id}"
                            data-file-id="${match.curseforge_file_id}">
                            <i class="bi bi-arrow-left-right me-1"></i> ${escapeHtml(I18N.convert_to_cf)}
                        </button>
                    </div>
                ` : '';

                return `
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark">${escapeHtml(I18N.badge_external)}</span>
                                <code class="fw-semibold">${escapeHtml(ext.file_name)}</code>
                                <span class="small text-muted">${formatSize(ext.size)}</span>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm cf-delete-ext-btn" data-file="${escapeHtml(ext.file_name)}">
                                <i class="bi bi-trash"></i> ${escapeHtml(I18N.delete_btn)}
                            </button>
                        </div>
                        ${matchHtml}
                    </div>
                `;
            }).join('')}</div>`;
        }
    }

    async function refreshInstalled() {
        const res = await apiRequest(routes.installed);
        state.installed = res.data;
        renderInstalled();
    }

    function renderCatalog(containerId, items, type) {
        const container = document.getElementById(containerId);
        if (!items || items.length === 0) {
            container.innerHTML = `<div class="col-12"><div class="alert alert-secondary text-center mb-0">${escapeHtml(I18N.no_results)}</div></div>`;
            return;
        }

        container.innerHTML = items.map(item => {
            const canInstall = Boolean(item.is_compatible && state.loaderSupported);
            const compatBadge = item.is_compatible
                ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>${escapeHtml(I18N.badge_compatible)}</span>`
                : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>${escapeHtml(I18N.badge_incompatible)}</span>`;

            const logoHtml = item.logo
                ? `<img src="${escapeHtml(item.logo)}" alt="" class="rounded flex-shrink-0" width="56" height="56" style="object-fit: cover;">`
                : `<div class="rounded bg-secondary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:56px;height:56px;"><i class="bi bi-box-seam fs-4 text-secondary"></i></div>`;

            const btnLabel = type === 'modpack' ? I18N.install_modpack : I18N.install_mod;
            const btnClass = type === 'modpack' ? 'cf-install-modpack-btn' : 'cf-install-mod-btn';
            const fileId = item.compatible_file ? item.compatible_file.id : '';

            return `
                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex gap-3 mb-2">
                                ${logoHtml}
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <h3 class="h6 fw-bold mb-1 text-truncate">${escapeHtml(item.name)}</h3>
                                        ${compatBadge}
                                    </div>
                                    <div class="small text-muted mb-1">
                                        ${escapeHtml(t('by_author', { author: item.author }))} &middot;
                                        <i class="bi bi-download"></i> ${formatDownloads(item.downloads)}
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted flex-grow-1 mb-3">${escapeHtml(item.summary)}</p>
                            <div class="d-flex justify-content-between align-items-center gap-2 pt-2 border-top">
                                <span class="small text-muted text-truncate">
                                    ${item.compatible_file ? escapeHtml(item.compatible_file.display_name) : escapeHtml(I18N.no_compatible_version)}
                                </span>
                                <button type="button"
                                    class="btn btn-sm ${canInstall ? 'btn-primary' : 'btn-secondary'} ${btnClass}"
                                    data-id="${item.id}"
                                    data-file-id="${fileId}"
                                    data-name="${escapeHtml(item.name)}"
                                    ${canInstall ? '' : 'disabled'}>
                                    <i class="bi bi-download me-1"></i> ${escapeHtml(btnLabel)}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function loadModsCatalog(page = 0) {
        if (!state.hasApiKey) return;
        const container = document.getElementById('cf-mods-results');
        container.innerHTML = `<div class="col-12 text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>${escapeHtml(I18N.loading_mods)}</div>`;

        try {
            const q = document.getElementById('cf-mods-query').value.trim();
            const url = `${routes.searchMods}?q=${encodeURIComponent(q)}&page=${page}`;
            const res = await apiRequest(url);
            state.modsPage = page;
            state.modsLoaded = true;
            renderCatalog('cf-mods-results', res.items, 'mod');

            const pag = document.getElementById('cf-mods-pagination');
            pag.style.removeProperty('display');
            document.getElementById('cf-mods-prev').disabled = page <= 0;
            document.getElementById('cf-mods-next').disabled = (res.items || []).length < 20;
            document.getElementById('cf-mods-page-info').textContent = t('page_label', { page: page + 1 });
        } catch (err) {
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${escapeHtml(err.message)}</div></div>`;
        }
    }

    async function loadModpacksCatalog(page = 0) {
        if (!state.hasApiKey) return;
        const container = document.getElementById('cf-modpacks-results');
        container.innerHTML = `<div class="col-12 text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>${escapeHtml(I18N.loading_modpacks)}</div>`;

        try {
            const q = document.getElementById('cf-modpacks-query').value.trim();
            const url = `${routes.searchModpacks}?q=${encodeURIComponent(q)}&page=${page}`;
            const res = await apiRequest(url);
            state.modpacksPage = page;
            state.modpacksLoaded = true;
            renderCatalog('cf-modpacks-results', res.items, 'modpack');

            const pag = document.getElementById('cf-modpacks-pagination');
            pag.style.removeProperty('display');
            document.getElementById('cf-modpacks-prev').disabled = page <= 0;
            document.getElementById('cf-modpacks-next').disabled = (res.items || []).length < 20;
            document.getElementById('cf-modpacks-page-info').textContent = t('page_label', { page: page + 1 });
        } catch (err) {
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${escapeHtml(err.message)}</div></div>`;
        }
    }

    // Tab events
    document.getElementById('tab-mods-btn').addEventListener('shown.bs.tab', () => {
        if (!state.modsLoaded && state.hasApiKey) loadModsCatalog(0);
    });
    document.getElementById('tab-modpacks-btn').addEventListener('shown.bs.tab', () => {
        if (!state.modpacksLoaded && state.hasApiKey) loadModpacksCatalog(0);
    });

    // Search forms & pagination
    document.getElementById('cf-search-mods-form').addEventListener('submit', e => {
        e.preventDefault();
        loadModsCatalog(0);
    });
    document.getElementById('cf-mods-prev').addEventListener('click', () => loadModsCatalog(Math.max(0, state.modsPage - 1)));
    document.getElementById('cf-mods-next').addEventListener('click', () => loadModsCatalog(state.modsPage + 1));

    document.getElementById('cf-search-modpacks-form').addEventListener('submit', e => {
        e.preventDefault();
        loadModpacksCatalog(0);
    });
    document.getElementById('cf-modpacks-prev').addEventListener('click', () => loadModpacksCatalog(Math.max(0, state.modpacksPage - 1)));
    document.getElementById('cf-modpacks-next').addEventListener('click', () => loadModpacksCatalog(state.modpacksPage + 1));

    // Check updates (both individual mods & installed modpacks)
    document.getElementById('cf-check-updates-btn').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await apiRequest(routes.checkUpdates, { method: 'POST', body: JSON.stringify({}) });
            state.updates = res.updates || {};
            state.modpackUpdates = res.modpack_updates || {};
            renderInstalled();
            const modCount = Object.values(state.updates).filter(u => u && u.has_update).length;
            const packCount = Object.values(state.modpackUpdates).filter(u => u && u.has_update).length;
            const totalCount = modCount + packCount;
            showFeedback(
                totalCount > 0 ? t('updates_found', { count: totalCount }) : I18N.all_up_to_date,
                totalCount > 0 ? 'info' : 'success'
            );
        } catch (err) {
            showFeedback(err.message, 'danger');
        } finally {
            this.disabled = false;
        }
    });

    // Scan external mods
    document.getElementById('cf-scan-external-btn').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await apiRequest(routes.scanExternal, { method: 'POST', body: JSON.stringify({}) });
            state.externalMatches = res.matches || {};
            renderInstalled();
            showFeedback(
                res.matched_count > 0
                    ? t('external_matched', { count: res.matched_count })
                    : I18N.external_no_match,
                res.matched_count > 0 ? 'success' : 'secondary'
            );
        } catch (err) {
            showFeedback(err.message, 'danger');
        } finally {
            this.disabled = false;
        }
    });

    // Delegated click handlers
    document.getElementById('curseforge-manager').addEventListener('click', async (e) => {
        const installModBtn = e.target.closest('.cf-install-mod-btn');
        if (installModBtn) {
            installModBtn.disabled = true;
            const orig = installModBtn.innerHTML;
            installModBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${escapeHtml(I18N.installing)}`;
            try {
                const res = await apiRequest(routes.installMod, {
                    method: 'POST',
                    body: JSON.stringify({
                        mod_id: Number(installModBtn.dataset.id),
                        file_id: installModBtn.dataset.fileId ? Number(installModBtn.dataset.fileId) : null,
                    }),
                });
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                installModBtn.disabled = false;
                installModBtn.innerHTML = orig;
            }
            return;
        }

        const progressBox = document.getElementById('cf-modpack-progress');
        const progressTitle = document.getElementById('cf-progress-title');
        const progressCounter = document.getElementById('cf-progress-counter');
        const progressBar = document.getElementById('cf-progress-bar');
        const progressFile = document.getElementById('cf-progress-file');

        function updateProgressUI(title, doneCount, totalCount, fileText = '') {
            progressBox.classList.remove('d-none');
            progressTitle.textContent = title;
            progressCounter.textContent = `${doneCount} / ${totalCount}`;
            const pct = totalCount > 0 ? Math.min(100, Math.round((doneCount / totalCount) * 100)) : 100;
            progressBar.style.width = `${pct}%`;
            progressBar.setAttribute('aria-valuenow', String(pct));
            progressFile.textContent = fileText;
        }

        function hideProgressUI() {
            progressBox.classList.add('d-none');
            progressBar.style.width = '0%';
            progressBar.setAttribute('aria-valuenow', '0');
            progressFile.textContent = '';
        }

        async function runChunkedModpackFlow({ modpackId, fileId, existingModpackId, packName }) {
            updateProgressUI(t('progress_preparing', { name: packName }), 0, 0, '');
            progressBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            const prep = await apiRequest(routes.prepareModpack, {
                method: 'POST',
                body: JSON.stringify({
                    modpack_id: modpackId || null,
                    file_id: fileId || null,
                    existing_modpack_id: existingModpackId || null,
                }),
            });

            const sessionId = prep.session_id;
            const total = Number(prep.total || 0);
            const filesList = Array.isArray(prep.files) ? prep.files : [];
            let offset = 0;
            let done = total === 0;

            updateProgressUI(
                t('progress_downloading', { name: prep.modpack_name || packName }),
                0,
                total,
                filesList[0] ? t('progress_current_file', { file: filesList[0].file_name }) : ''
            );

            while (!done) {
                const nextFileName = filesList[offset] ? filesList[offset].file_name : '';
                if (nextFileName) {
                    updateProgressUI(
                        t('progress_downloading', { name: prep.modpack_name || packName }),
                        offset,
                        total,
                        t('progress_current_file', { file: nextFileName })
                    );
                }

                const batchRes = await apiRequest(routes.installModpackBatch, {
                    method: 'POST',
                    body: JSON.stringify({
                        session_id: sessionId,
                        offset,
                        limit: 2,
                    }),
                });

                offset = Number(batchRes.next_offset || total);
                done = Boolean(batchRes.done);
                const lastFiles = Array.isArray(batchRes.current_files) ? batchRes.current_files : [];
                updateProgressUI(
                    t('progress_downloading', { name: prep.modpack_name || packName }),
                    offset,
                    total,
                    lastFiles.length > 0 ? t('progress_current_file', { file: lastFiles[lastFiles.length - 1] }) : ''
                );
            }

            updateProgressUI(t('progress_finalizing', { name: prep.modpack_name || packName }), total, total, '');

            return apiRequest(routes.finalizeModpack, {
                method: 'POST',
                body: JSON.stringify({ session_id: sessionId }),
            });
        }

        const installPackBtn = e.target.closest('.cf-install-modpack-btn');
        if (installPackBtn) {
            const packName = installPackBtn.dataset.name || 'Modpack';
            if (!confirm(t('confirm_install_modpack', { name: packName }))) return;
            installPackBtn.disabled = true;
            const orig = installPackBtn.innerHTML;
            installPackBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${escapeHtml(I18N.installing_modpack)}`;
            try {
                const res = await runChunkedModpackFlow({
                    modpackId: Number(installPackBtn.dataset.id),
                    fileId: installPackBtn.dataset.fileId ? Number(installPackBtn.dataset.fileId) : null,
                    existingModpackId: null,
                    packName,
                });
                const s = res.data || {};
                const details = t('modpack_install_details', {
                    overrides: s.overrides_count || 0,
                    skipped: s.skipped_count || 0,
                });
                showFeedback(`${res.message} ${details}`, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                hideProgressUI();
                installPackBtn.disabled = false;
                installPackBtn.innerHTML = orig;
            }
            return;
        }

        const updatePackBtn = e.target.closest('.cf-update-modpack-btn');
        if (updatePackBtn) {
            const packName = updatePackBtn.dataset.name || 'Modpack';
            if (!confirm(t('confirm_update_modpack', { name: packName }))) return;
            updatePackBtn.disabled = true;
            const orig = updatePackBtn.innerHTML;
            updatePackBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${escapeHtml(I18N.updating)}`;
            try {
                const packId = Number(updatePackBtn.dataset.id);
                const cfPackId = updatePackBtn.dataset.curseforgeModpackId ? Number(updatePackBtn.dataset.curseforgeModpackId) : null;
                const res = await runChunkedModpackFlow({
                    modpackId: cfPackId,
                    fileId: updatePackBtn.dataset.fileId ? Number(updatePackBtn.dataset.fileId) : null,
                    existingModpackId: packId,
                    packName,
                });
                delete state.modpackUpdates[packId];
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
                updatePackBtn.disabled = false;
                updatePackBtn.innerHTML = orig;
            } finally {
                hideProgressUI();
            }
            return;
        }

        const deletePackBtn = e.target.closest('.cf-delete-modpack-btn');
        if (deletePackBtn) {
            if (!confirm(t('confirm_delete_modpack', { name: deletePackBtn.dataset.name }))) return;
            deletePackBtn.disabled = true;
            try {
                const packId = deletePackBtn.dataset.id;
                const res = await apiRequest(`${routes.modpacksBase}/${packId}`, { method: 'DELETE' });
                delete state.modpackUpdates[packId];
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
                deletePackBtn.disabled = false;
            }
            return;
        }

        const updateModBtn = e.target.closest('.cf-update-mod-btn');
        if (updateModBtn) {
            updateModBtn.disabled = true;
            try {
                const modId = updateModBtn.dataset.id;
                const res = await apiRequest(`${routes.updateModBase}/${modId}/update`, {
                    method: 'POST',
                    body: JSON.stringify({
                        file_id: updateModBtn.dataset.fileId ? Number(updateModBtn.dataset.fileId) : null,
                    }),
                });
                delete state.updates[modId];
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                updateModBtn.disabled = false;
            }
            return;
        }

        const deleteModBtn = e.target.closest('.cf-delete-mod-btn');
        if (deleteModBtn) {
            if (!confirm(t('confirm_delete_mod', { name: deleteModBtn.dataset.name }))) return;
            deleteModBtn.disabled = true;
            try {
                const res = await apiRequest(`${routes.updateModBase}/${deleteModBtn.dataset.id}`, { method: 'DELETE' });
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                deleteModBtn.disabled = false;
            }
            return;
        }

        const deleteExtBtn = e.target.closest('.cf-delete-ext-btn');
        if (deleteExtBtn) {
            const fileName = deleteExtBtn.dataset.file;
            if (!confirm(t('confirm_delete_external', { name: fileName }))) return;
            deleteExtBtn.disabled = true;
            try {
                const res = await apiRequest(routes.deleteExternal, {
                    method: 'DELETE',
                    body: JSON.stringify({ file_name: fileName }),
                });
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                deleteExtBtn.disabled = false;
            }
            return;
        }

        const reconcileBtn = e.target.closest('.cf-reconcile-btn');
        if (reconcileBtn) {
            reconcileBtn.disabled = true;
            try {
                const fileName = reconcileBtn.dataset.file;
                const res = await apiRequest(routes.reconcileExternal, {
                    method: 'POST',
                    body: JSON.stringify({
                        file_name: fileName,
                        mod_id: Number(reconcileBtn.dataset.modId),
                        file_id: Number(reconcileBtn.dataset.fileId),
                    }),
                });
                delete state.externalMatches[fileName];
                showFeedback(res.message, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                reconcileBtn.disabled = false;
            }
        }
    });

    renderInstalled();
})();
</script>
@endpush
