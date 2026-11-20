@extends('layouts.admin')

@section('title', 'Gestionnaire CurseForge')
@section('page-title', 'Gestionnaire CurseForge')

@section('content')
<div class="container-fluid p-0" id="curseforge-manager">
    {{-- Instance Status Banner --}}
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Instance active</div>
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
                        MC {{ $mcVersion ?? 'Non définie' }}
                    </span>

                    @if($loaderSupported)
                        <span class="badge bg-success fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-cpu me-1"></i>
                            {{ ucfirst($resolvedLoader) }}
                        </span>
                    @else
                        <span class="badge bg-warning text-dark fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Loader : {{ ucfirst($rawLoader) }} (non supporté par CurseForge)
                        </span>
                    @endif

                    @if($hasApiKey)
                        <span id="cf-api-badge" class="badge bg-success-subtle text-success border border-success-subtle fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-key-fill me-1"></i> Clé API configurée
                        </span>
                    @else
                        <span id="cf-api-badge" class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 fw-normal px-3 py-2">
                            <i class="bi bi-key me-1"></i> Clé API non configurée
                        </span>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#cfApiKeyCollapse" aria-expanded="{{ $hasApiKey ? 'false' : 'true' }}">
                    <i class="bi bi-gear me-1"></i> Configurer la clé API
                </button>
                <a href="{{ route('admin.loader') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-sliders me-1"></i> Configurer le Loader
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
                            <i class="bi bi-key me-1"></i> Clé API CurseForge (<code>x-api-key</code>)
                        </label>
                        <input
                            type="password"
                            name="curseforge_api_key"
                            id="curseforge_api_key"
                            class="form-control"
                            placeholder="{{ $hasApiKey ? '•••••••••••••••••••••••• (laisser vide pour effacer ou saisir une nouvelle clé)' : 'Collez votre clé API CurseForge ($2a$10$...)' }}"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i> Enregistrer la clé
                        </button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    Obtenez une clé API gratuite sur la console développeur CurseForge. Variable d'environnement fallback : <code>CURSEFORGE_API_KEY</code>.
                </div>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if(!$hasApiKey)
        <div id="cf-no-key-alert" class="alert alert-warning d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div>
                <strong>Clé API CurseForge requise :</strong> configurez votre clé API ci-dessus pour parcourir les mods, installer des modpacks, vérifier les mises à jour ou réconcilier vos mods externes.
            </div>
        </div>
    @endif

    @if(!$loaderSupported)
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-shield-exclamation fs-5"></i>
            <div>
                <strong>Loader incompatible :</strong> l'instance active utilise <code>{{ $rawLoader }}</code>. L'installation de mods et modpacks CurseForge nécessite <strong>Forge</strong>, <strong>Fabric</strong>, <strong>Quilt</strong> ou <strong>NeoForge</strong>.
            </div>
        </div>
    @endif

    <div id="cf-feedback-zone"></div>

    {{-- Navigation Tabs --}}
    <ul class="nav nav-tabs mb-4" id="cfTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-installed-btn" data-bs-toggle="tab" data-bs-target="#tab-installed" type="button" role="tab">
                <i class="bi bi-box-seam me-1"></i> Mods installés
                <span class="badge bg-primary ms-1" id="cf-installed-total-badge">{{ count($installed['curseforge']) + count($installed['external']) }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-mods-btn" data-bs-toggle="tab" data-bs-target="#tab-mods" type="button" role="tab">
                <i class="bi bi-search me-1"></i> Parcourir les Mods
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-modpacks-btn" data-bs-toggle="tab" data-bs-target="#tab-modpacks" type="button" role="tab">
                <i class="bi bi-archive me-1"></i> Modpacks
            </button>
        </li>
    </ul>

    <div class="tab-content" id="cfTabContent">
        {{-- TAB 1: INSTALLED MODS --}}
        <div class="tab-pane fade show active" id="tab-installed" role="tabpanel">
            {{-- CurseForge Mods Section --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-fire text-warning me-1"></i> Mods CurseForge
                        <span class="badge bg-secondary ms-1" id="cf-tracked-count">{{ count($installed['curseforge']) }}</span>
                    </h2>
                    <button type="button" id="cf-check-updates-btn" class="btn btn-outline-primary btn-sm" {{ !$hasApiKey || !$loaderSupported ? 'disabled' : '' }}>
                        <i class="bi bi-arrow-repeat me-1"></i> Vérifier les mises à jour
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="cf-tracked-list">
                        @if(empty($installed['curseforge']))
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                                Aucun mod CurseForge suivi dans cette instance.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- External Mods Section --}}
            <div class="card shadow-sm border-warning-subtle">
                <div class="card-header bg-warning-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-0 text-body">
                            <i class="bi bi-file-earmark-zip me-1"></i> Externe
                            <span class="badge bg-warning text-dark ms-1" id="cf-external-count">{{ count($installed['external']) }}</span>
                        </h2>
                        <small class="text-muted">Fichiers <code>.jar</code> présents dans <code>mods/</code> sans suivi CurseForge (pas de mise à jour automatique).</small>
                    </div>
                    <button type="button" id="cf-scan-external-btn" class="btn btn-warning btn-sm" {{ !$hasApiKey ? 'disabled' : '' }}>
                        <i class="bi bi-fingerprint me-1"></i> Analyser &amp; Réconcilier (CurseForge)
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="cf-external-list">
                        @if(empty($installed['external']))
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-check-circle fs-2 d-block mb-2"></i>
                                Aucun mod externe non suivi détecté dans <code>mods/</code>.
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
                                <input type="text" id="cf-mods-query" class="form-control" placeholder="Rechercher un mod CurseForge (vide = tendances populaires)...">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-primary w-100" {{ !$hasApiKey ? 'disabled' : '' }}>
                                <i class="bi bi-search me-1"></i> Rechercher
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-funnel me-1"></i>
                        Filtrage strict actif : <strong>MC {{ $mcVersion ?? '?' }}</strong> + <strong>{{ $loaderSupported ? ucfirst($resolvedLoader) : 'Loader non supporté' }}</strong>. Les mods incompatibles ne peuvent pas être installés.
                    </div>
                </div>
            </div>

            <div id="cf-mods-results" class="row g-3"></div>
            <div class="d-flex justify-content-between align-items-center mt-4" id="cf-mods-pagination" style="display: none !important;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-mods-prev"><i class="bi bi-chevron-left"></i> Précédent</button>
                <span class="text-muted small" id="cf-mods-page-info"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-mods-next">Suivant <i class="bi bi-chevron-right"></i></button>
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
                                <input type="text" id="cf-modpacks-query" class="form-control" placeholder="Rechercher un modpack CurseForge (vide = les plus populaires)...">
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-primary w-100" {{ !$hasApiKey ? 'disabled' : '' }}>
                                <i class="bi bi-search me-1"></i> Rechercher
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        L'installation d'un modpack télécharge automatiquement tous les mods du <code>manifest.json</code> (et les <code>overrides/mods/</code>) dans le dossier <code>mods/</code> de l'instance active.
                    </div>
                </div>
            </div>

            <div id="cf-modpacks-results" class="row g-3"></div>
            <div class="d-flex justify-content-between align-items-center mt-4" id="cf-modpacks-pagination" style="display: none !important;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-modpacks-prev"><i class="bi bi-chevron-left"></i> Précédent</button>
                <span class="text-muted small" id="cf-modpacks-page-info"></span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="cf-modpacks-next">Suivant <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = {
        installed: @json(route('admin.curseforge.installed')),
        searchMods: @json(route('admin.curseforge.search.mods')),
        searchModpacks: @json(route('admin.curseforge.search.modpacks')),
        installMod: @json(route('admin.curseforge.mods.install')),
        installModpack: @json(route('admin.curseforge.modpacks.install')),
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
        if (b >= 1048576) return (b / 1048576).toFixed(2) + ' Mo';
        if (b >= 1024) return (b / 1024).toFixed(1) + ' Ko';
        return b + ' o';
    }

    function showFeedback(message, type = 'success') {
        const zone = document.getElementById('cf-feedback-zone');
        zone.innerHTML = `
            <div class="alert alert-${escapeHtml(type)} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
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
            throw new Error(data.message || `Erreur HTTP ${resp.status}`);
        }
        return data;
    }

    function renderInstalled() {
        const cfList = document.getElementById('cf-tracked-list');
        const extList = document.getElementById('cf-external-list');
        const cfMods = state.installed.curseforge || [];
        const extMods = state.installed.external || [];

        document.getElementById('cf-tracked-count').textContent = String(cfMods.length);
        document.getElementById('cf-external-count').textContent = String(extMods.length);
        document.getElementById('cf-installed-total-badge').textContent = String(cfMods.length + extMods.length);

        if (cfMods.length === 0) {
            cfList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
                    Aucun mod CurseForge suivi dans cette instance.
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
                           <i class="bi bi-cloud-arrow-down me-1"></i> Mettre à jour (${escapeHtml(upd.latest_version || 'Nouvelle version')})
                       </button>`
                    : '';

                const missingBadge = mod.exists_on_disk === false
                    ? `<span class="badge bg-danger ms-1">Fichier absent</span>`
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
                                <i class="bi bi-trash"></i> Supprimer
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
                    Aucun mod externe non suivi détecté dans <code>mods/</code>.
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
                                <strong>Correspondance CurseForge :</strong> ${escapeHtml(match.name)}
                                <span class="text-muted">(${escapeHtml(match.version)})</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-sm cf-reconcile-btn"
                            data-file="${escapeHtml(ext.file_name)}"
                            data-mod-id="${match.curseforge_mod_id}"
                            data-file-id="${match.curseforge_file_id}">
                            <i class="bi bi-arrow-left-right me-1"></i> Convertir vers CurseForge
                        </button>
                    </div>
                ` : '';

                return `
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark">Externe</span>
                                <code class="fw-semibold">${escapeHtml(ext.file_name)}</code>
                                <span class="small text-muted">${formatSize(ext.size)}</span>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm cf-delete-ext-btn" data-file="${escapeHtml(ext.file_name)}">
                                <i class="bi bi-trash"></i> Supprimer
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
            container.innerHTML = `<div class="col-12"><div class="alert alert-secondary text-center mb-0">Aucun résultat trouvé sur CurseForge.</div></div>`;
            return;
        }

        container.innerHTML = items.map(item => {
            const canInstall = Boolean(item.is_compatible && state.loaderSupported);
            const compatBadge = item.is_compatible
                ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Compatible</span>`
                : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Incompatible</span>`;

            const logoHtml = item.logo
                ? `<img src="${escapeHtml(item.logo)}" alt="" class="rounded flex-shrink-0" width="56" height="56" style="object-fit: cover;">`
                : `<div class="rounded bg-secondary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:56px;height:56px;"><i class="bi bi-box-seam fs-4 text-secondary"></i></div>`;

            const btnLabel = type === 'modpack' ? 'Installer le modpack' : 'Installer';
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
                                        Par <strong>${escapeHtml(item.author)}</strong> &middot;
                                        <i class="bi bi-download"></i> ${formatDownloads(item.downloads)}
                                    </div>
                                </div>
                            </div>
                            <p class="small text-muted flex-grow-1 mb-3">${escapeHtml(item.summary)}</p>
                            <div class="d-flex justify-content-between align-items-center gap-2 pt-2 border-top">
                                <span class="small text-muted text-truncate">
                                    ${item.compatible_file ? escapeHtml(item.compatible_file.display_name) : 'Aucune version compatible'}
                                </span>
                                <button type="button"
                                    class="btn btn-sm ${canInstall ? 'btn-primary' : 'btn-secondary'} ${btnClass}"
                                    data-id="${item.id}"
                                    data-file-id="${fileId}"
                                    data-name="${escapeHtml(item.name)}"
                                    ${canInstall ? '' : 'disabled'}>
                                    <i class="bi bi-download me-1"></i> ${btnLabel}
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
        container.innerHTML = `<div class="col-12 text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Chargement des mods CurseForge...</div>`;

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
            document.getElementById('cf-mods-page-info').textContent = `Page ${page + 1}`;
        } catch (err) {
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${escapeHtml(err.message)}</div></div>`;
        }
    }

    async function loadModpacksCatalog(page = 0) {
        if (!state.hasApiKey) return;
        const container = document.getElementById('cf-modpacks-results');
        container.innerHTML = `<div class="col-12 text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Chargement des modpacks CurseForge...</div>`;

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
            document.getElementById('cf-modpacks-page-info').textContent = `Page ${page + 1}`;
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

    // Check updates
    document.getElementById('cf-check-updates-btn').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await apiRequest(routes.checkUpdates, { method: 'POST', body: JSON.stringify({}) });
            state.updates = res.updates || {};
            renderInstalled();
            const count = Object.values(state.updates).filter(u => u && u.has_update).length;
            showFeedback(count > 0 ? `${count} mise(s) à jour compatible(s) trouvée(s).` : 'Tous vos mods CurseForge sont à jour.', count > 0 ? 'info' : 'success');
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
                    ? `${res.matched_count} mod(s) externe(s) identifié(s) sur CurseForge.`
                    : 'Aucune correspondance exacte trouvée sur CurseForge pour vos mods externes.',
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
            installModBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Installation...';
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

        const installPackBtn = e.target.closest('.cf-install-modpack-btn');
        if (installPackBtn) {
            if (!confirm(`Installer le modpack "${installPackBtn.dataset.name}" dans l'instance active ?`)) return;
            installPackBtn.disabled = true;
            const orig = installPackBtn.innerHTML;
            installPackBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Installation du modpack...';
            try {
                const res = await apiRequest(routes.installModpack, {
                    method: 'POST',
                    body: JSON.stringify({
                        modpack_id: Number(installPackBtn.dataset.id),
                        file_id: installPackBtn.dataset.fileId ? Number(installPackBtn.dataset.fileId) : null,
                    }),
                });
                const s = res.data || {};
                showFeedback(`${res.message} (Overrides : ${s.overrides_count || 0}, Ignorés : ${s.skipped_count || 0})`, 'success');
                await refreshInstalled();
            } catch (err) {
                showFeedback(err.message, 'danger');
            } finally {
                installPackBtn.disabled = false;
                installPackBtn.innerHTML = orig;
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
            if (!confirm(`Supprimer le mod "${deleteModBtn.dataset.name}" de l'instance active ?`)) return;
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
            if (!confirm(`Supprimer le mod externe "${fileName}" ?`)) return;
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
