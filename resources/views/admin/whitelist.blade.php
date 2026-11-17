@extends('layouts.admin')

@section('title', __('messages.whitelist.title'))
@section('page-title', __('messages.whitelist.header'))

@section('content')
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="{{ route('admin.whitelist.store') }}" method="POST" id="whitelistForm">
            @csrf

            <div class="panel-muted-surface p-3 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-7">
                        <h2 class="panel-section-title mb-2">
                            <i class="bi bi-power"></i>{{ __('messages.whitelist.activation', ['name' => $activeInstance->name ?? 'Default']) }}
                        </h2>
                        <div class="form-check form-switch">
                            <input type="hidden" name="whitelist" value="0">
                            <input type="checkbox" class="form-check-input" id="whitelist" name="whitelist" value="1"
                                   {{ ($activeInstance->whitelist_activate ?? $securityOptions->whitelist ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="whitelist">{{ __('messages.whitelist.enable') }}</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label for="scope" class="form-label">{{ __('messages.whitelist.scope_label') }}</label>
                        <select class="form-select" id="scope" name="scope">
                            <option value="instance">{{ __('messages.whitelist.scope_instance', ['name' => $activeInstance->name ?? 'Default']) }}</option>
                            <option value="global">{{ __('messages.whitelist.scope_global') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Manual Minecraft Usernames --}}
            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-person-plus"></i>{{ __('messages.whitelist.manual_users_section') }}</h2>
                <div class="mb-2">
                    <label for="manual_users" class="form-label">{{ __('messages.whitelist.manual_users_label') }}</label>
                    <textarea class="form-control" id="manual_users" name="manual_users" rows="2"
                              placeholder="{{ __('messages.whitelist.manual_users_placeholder') }}"></textarea>
                    <div class="form-text">{{ __('messages.whitelist.manual_users_hint') }}</div>
                </div>
            </div>

            {{-- Panel Roles & Microsoft Players --}}
            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="panel-muted-surface p-3 h-100">
                        <h2 class="panel-section-title"><i class="bi bi-person-badge"></i>{{ __('messages.whitelist.panel_roles_section') }}</h2>
                        @php
                            $unwhitelistedPanelRoles = ($panelRoles ?? collect())->filter(fn ($r) => !in_array($r->name, $whitelistedRoleNames ?? [], true));
                        @endphp
                        @if($unwhitelistedPanelRoles->isEmpty())
                            <p class="text-muted small mb-0">{{ __('messages.whitelist.no_roles_available') }}</p>
                        @else
                            <div class="row">
                                @foreach($unwhitelistedPanelRoles as $pRole)
                                    <div class="col-sm-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="panel_roles[]"
                                                   value="{{ $pRole->name }}" id="panel_role_{{ $pRole->id }}">
                                            <label class="form-check-label d-inline-flex align-items-center gap-1" for="panel_role_{{ $pRole->id }}">
                                                <span class="badge" style="background-color: {{ $pRole->color }};">{{ $pRole->name }}</span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="panel-muted-surface p-3 h-100">
                        <h2 class="panel-section-title"><i class="bi bi-controller"></i>{{ __('messages.whitelist.microsoft_players_section') }}</h2>
                        @php
                            $unwhitelistedMsPlayers = ($microsoftPlayers ?? collect())->filter(fn ($p) => !in_array($p->username, $whitelistedUsernames ?? [], true));
                        @endphp
                        @if($unwhitelistedMsPlayers->isEmpty())
                            <p class="text-muted small mb-0">{{ __('messages.whitelist.no_microsoft_players') }}</p>
                        @else
                            <div class="row scroll-panel-sm">
                                @foreach($unwhitelistedMsPlayers as $msPlayer)
                                    <div class="col-sm-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="whitelist_users[]"
                                                   value="{{ $msPlayer->username }}" id="ms_player_{{ $msPlayer->id }}">
                                            <label class="form-check-label" for="ms_player_{{ $msPlayer->id }}">
                                                <strong>{{ $msPlayer->username }}</strong>
                                                @if($msPlayer->role)
                                                    <span class="badge ms-1" style="background-color: {{ $msPlayer->role->color }};">{{ $msPlayer->role->name }}</span>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Azuriom Users & Roles (when configured) --}}
            @if($hasAzuriomApi)
                <div class="panel-muted-surface p-3 mb-4">
                    <h2 class="panel-section-title"><i class="bi bi-people"></i>{{ __('messages.whitelist.azuriom_users') }}</h2>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary btn-icon" id="refreshUsersBtn">
                            <i class="bi bi-arrow-clockwise"></i>
                            {{ __('messages.common.refresh_list') }}
                        </button>
                        <span class="text-muted align-self-center small" id="usersCacheInfo"></span>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="userSearchFilter"
                               placeholder="{{ __('messages.whitelist.filter_users') }}" disabled>
                    </div>

                    <div id="usersListContainer" class="row scroll-panel">
                        <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-arrow-clockwise fs-1"></i>
                            <p>{{ __('messages.whitelist.click_refresh') }}</p>
                        </div>
                    </div>
                </div>

                <div class="panel-muted-surface p-3 mb-4">
                    <h2 class="panel-section-title"><i class="bi bi-person-badge"></i>{{ __('messages.whitelist.azuriom_roles') }}</h2>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-primary btn-icon" id="refreshRolesBtn">
                            <i class="bi bi-arrow-clockwise"></i>
                            {{ __('messages.common.refresh_list') }}
                        </button>
                        <span class="text-muted align-self-center small" id="rolesCacheInfo"></span>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="roleSearchFilter"
                               placeholder="{{ __('messages.whitelist.filter_roles') }}" disabled>
                    </div>

                    <div id="rolesListContainer" class="row scroll-panel-sm">
                        <div class="col-12 text-center text-muted py-4">
                            <i class="bi bi-arrow-clockwise fs-1"></i>
                            <p>{{ __('messages.whitelist.click_refresh') }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    {{ __('messages.whitelist.api_not_configured') }}
                </div>
            @endif

            <button type="submit" class="btn btn-primary btn-icon">
                <i class="bi bi-save"></i>
                {{ __('messages.common.save') }}
            </button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.whitelist.users_in_whitelist') }}</h2>
            </div>
            <div class="card-body">
                @if($users->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-person-x"></i>
                        <span>{{ __('messages.whitelist.no_users') }}</span>
                    </div>
                @else
                    <div class="panel-list" id="whitelistedUsersList">
                        @foreach($users as $user)
                            <div class="panel-list-item">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-truncate whitelist-username">{{ $user->users }}</span>
                                    @if($user->instance_id === null)
                                        <span class="badge text-bg-primary">{{ __('messages.whitelist.global_badge') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('messages.whitelist.instance_badge') }}</span>
                                    @endif
                                </div>
                                <form action="{{ route('admin.whitelist.destroyUser', $user->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="{{ __('messages.common.confirm_delete') }}">
                                        <i class="bi bi-trash"></i>
                                        {{ __('messages.common.delete') }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.whitelist.roles_in_whitelist') }}</h2>
            </div>
            <div class="card-body">
                @if($roles->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-person-badge"></i>
                        <span>{{ __('messages.whitelist.no_roles') }}</span>
                    </div>
                @else
                    <div class="panel-list" id="whitelistedRolesList">
                        @foreach($roles as $role)
                            <div class="panel-list-item">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-truncate whitelist-rolename">{{ $role->role }}</span>
                                    @if($role->instance_id === null)
                                        <span class="badge text-bg-primary">{{ __('messages.whitelist.global_badge') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('messages.whitelist.instance_badge') }}</span>
                                    @endif
                                </div>
                                <form action="{{ route('admin.whitelist.destroyRole', $role->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-confirm="{{ __('messages.common.confirm_delete') }}">
                                        <i class="bi bi-trash"></i>
                                        {{ __('messages.common.delete') }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const CACHE_KEY_USERS = 'whitelist_users_cache';
    const CACHE_KEY_ROLES = 'whitelist_roles_cache';

    const whitelistedUsers = new Set();
    const whitelistedRoles = new Set();

    document.querySelectorAll('#whitelistedUsersList .whitelist-username').forEach(el => {
        whitelistedUsers.add(el.textContent.trim());
    });
    document.querySelectorAll('#whitelistedRolesList .whitelist-rolename').forEach(el => {
        whitelistedRoles.add(el.textContent.trim());
    });

    function filterWhitelistedUsers(users) {
        return users.filter(u => !whitelistedUsers.has(u.name));
    }

    function filterWhitelistedRoles(roles) {
        return roles.filter(r => !whitelistedRoles.has(r.name));
    }

    const refreshUsersBtn = document.getElementById('refreshUsersBtn');
    const userSearchFilter = document.getElementById('userSearchFilter');
    const usersListContainer = document.getElementById('usersListContainer');
    const usersCacheInfo = document.getElementById('usersCacheInfo');
    let usersCache = loadCache(CACHE_KEY_USERS);

    if (refreshUsersBtn) {
        if (usersCache && usersCache.data) {
            renderUsers(filterWhitelistedUsers(usersCache.data));
            updateCacheInfo(usersCacheInfo, usersCache.timestamp);
            userSearchFilter.disabled = false;
        }

        refreshUsersBtn.addEventListener('click', function() {
            refreshUsersBtn.disabled = true;
            refreshUsersBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> {{ __('messages.common.loading') }}';

            fetch('{{ route('admin.whitelist.fetchUsers') }}')
                .then(res => res.json())
                .then(users => {
                    usersCache = { data: users, timestamp: Date.now() };
                    saveCache(CACHE_KEY_USERS, usersCache);
                    renderUsers(filterWhitelistedUsers(users));
                    updateCacheInfo(usersCacheInfo, usersCache.timestamp);
                    userSearchFilter.disabled = false;
                })
                .catch(err => {
                    console.error('{{ __('messages.common.error') }}:', err);
                    usersListContainer.innerHTML = '<div class="col-12 text-danger">{{ __('messages.common.error') }}</div>';
                })
                .finally(() => {
                    refreshUsersBtn.disabled = false;
                    refreshUsersBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> {{ __('messages.common.refresh_list') }}';
                });
        });

        userSearchFilter.addEventListener('input', function() {
            if (usersCache && usersCache.data) {
                const query = this.value.toLowerCase();
                const filtered = filterWhitelistedUsers(usersCache.data).filter(u => u.name.toLowerCase().includes(query));
                renderUsers(filtered);
            }
        });
    }

    function renderUsers(users) {
        if (users.length === 0) {
            usersListContainer.innerHTML = '<div class="col-12 text-muted">{{ __('messages.whitelist.no_users_available') }}</div>';
            return;
        }
        usersListContainer.innerHTML = users.map(user => `
            <div class="col-md-4 mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="whitelist_users[]"
                           value="${escapeHtml(user.name)}" id="user_${user.id}">
                    <label class="form-check-label" for="user_${user.id}">
                        <strong class="text-truncate">${escapeHtml(user.name)}</strong>
                        ${user.is_admin ? '<span class="badge text-bg-danger ms-1">Admin</span>' :
                            `<span class="badge text-bg-secondary ms-1">${escapeHtml(user.role)}</span>`}
                    </label>
                </div>
            </div>
        `).join('');
    }

    const refreshRolesBtn = document.getElementById('refreshRolesBtn');
    const roleSearchFilter = document.getElementById('roleSearchFilter');
    const rolesListContainer = document.getElementById('rolesListContainer');
    const rolesCacheInfo = document.getElementById('rolesCacheInfo');
    let rolesCache = loadCache(CACHE_KEY_ROLES);

    if (refreshRolesBtn) {
        if (rolesCache && rolesCache.data) {
            renderRoles(filterWhitelistedRoles(rolesCache.data));
            updateCacheInfo(rolesCacheInfo, rolesCache.timestamp);
            roleSearchFilter.disabled = false;
        }

        refreshRolesBtn.addEventListener('click', function() {
            refreshRolesBtn.disabled = true;
            refreshRolesBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> {{ __('messages.common.loading') }}';

            fetch('{{ route('admin.whitelist.fetchRoles') }}')
                .then(res => res.json())
                .then(roles => {
                    rolesCache = { data: roles, timestamp: Date.now() };
                    saveCache(CACHE_KEY_ROLES, rolesCache);
                    renderRoles(filterWhitelistedRoles(roles));
                    updateCacheInfo(rolesCacheInfo, rolesCache.timestamp);
                    roleSearchFilter.disabled = false;
                })
                .catch(err => {
                    console.error('{{ __('messages.common.error') }}:', err);
                    rolesListContainer.innerHTML = '<div class="col-12 text-danger">{{ __('messages.common.error') }}</div>';
                })
                .finally(() => {
                    refreshRolesBtn.disabled = false;
                    refreshRolesBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> {{ __('messages.common.refresh_list') }}';
                });
        });

        roleSearchFilter.addEventListener('input', function() {
            if (rolesCache && rolesCache.data) {
                const query = this.value.toLowerCase();
                const filtered = filterWhitelistedRoles(rolesCache.data).filter(r => r.name.toLowerCase().includes(query));
                renderRoles(filtered);
            }
        });
    }

    function renderRoles(roles) {
        if (roles.length === 0) {
            rolesListContainer.innerHTML = '<div class="col-12 text-muted">{{ __('messages.whitelist.no_roles_available') }}</div>';
            return;
        }
        rolesListContainer.innerHTML = roles.map(role => `
            <div class="col-md-4 mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="azuriom_roles[]"
                           value="${escapeHtml(role.name)}" id="role_${role.id}">
                    <label class="form-check-label" for="role_${role.id}">
                        <strong>${escapeHtml(role.name)}</strong>
                        ${role.is_admin ? '<span class="badge text-bg-danger ms-1">Admin</span>' : ''}
                        <span class="badge text-bg-secondary ms-1">Power: ${Number(role.power || 0)}</span>
                    </label>
                </div>
            </div>
        `).join('');
    }

    function loadCache(key) {
        try {
            const cached = localStorage.getItem(key);
            return cached ? JSON.parse(cached) : null;
        } catch (e) {
            return null;
        }
    }

    function saveCache(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.warn('{{ __('messages.common.error') }}:', e);
        }
    }

    function updateCacheInfo(element, timestamp) {
        if (!element) return;
        const date = new Date(timestamp);
        const diff = Math.floor((Date.now() - date.getTime()) / 1000 / 60);

        if (diff < 1) {
            element.textContent = '{{ __('messages.whitelist.cache_just_now') }}';
        } else if (diff < 60) {
            element.textContent = `({{ __('messages.whitelist.cache_updated') }} ${diff} min)`;
        } else {
            element.textContent = `({{ __('messages.whitelist.cache_updated') }} ${date.toLocaleDateString()} ${date.toLocaleTimeString()})`;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>
@endpush
