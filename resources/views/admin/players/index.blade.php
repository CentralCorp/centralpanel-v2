@extends('layouts.admin')

@section('title', __('messages.players.title'))

@section('content')
@php
    $apiBaseUrl = rtrim(url('/'), '/') . '/api/v1/players';
    $currentTab = in_array($activeTab ?? 'players', ['players', 'api', 'docs'], true) ? $activeTab : 'players';
@endphp

<x-admin.page-header :title="__('messages.players.header')" :subtitle="__('messages.players.subtitle')" icon="bi-controller">
    <button type="button" class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addPlayerModal">
        <i class="bi bi-person-plus"></i>
        {{ __('messages.players.add_player') }}
    </button>
</x-admin.page-header>

<ul class="nav nav-tabs mb-4" id="playersTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $currentTab === 'players' ? 'active' : '' }}" id="tab-players-btn"
                data-bs-toggle="tab" data-bs-target="#tab-players" type="button" role="tab">
            <i class="bi bi-people me-1"></i>{{ __('messages.players.tab_players') }}
            <span class="badge text-bg-secondary ms-1">{{ $players->count() }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $currentTab === 'api' ? 'active' : '' }}" id="tab-api-btn"
                data-bs-toggle="tab" data-bs-target="#tab-api" type="button" role="tab">
            <i class="bi bi-key me-1"></i>{{ __('messages.players.tab_api') }}
            <span class="badge {{ $playerApiEnabled ? 'text-bg-success' : 'text-bg-secondary' }} ms-1">
                {{ $playerApiEnabled ? __('messages.players.api_status_enabled') : __('messages.players.api_status_disabled') }}
            </span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $currentTab === 'docs' ? 'active' : '' }}" id="tab-docs-btn"
                data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
            <i class="bi bi-book me-1"></i>{{ __('messages.players.tab_api_docs') }}
        </button>
    </li>
</ul>

<div class="tab-content" id="playersTabsContent">
    {{-- TAB 1: PLAYERS --}}
    <div class="tab-pane fade {{ $currentTab === 'players' ? 'show active' : '' }}" id="tab-players" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="{{ route('admin.players') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="q" class="form-label">{{ __('messages.common.search') }}</label>
                        <input type="text" class="form-control" id="q" name="q" value="{{ $search }}"
                               placeholder="{{ __('messages.players.search_placeholder') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="role_id" class="form-label">{{ __('messages.players.role') }}</label>
                        <select class="form-select" id="role_id" name="role_id">
                            <option value="">{{ __('messages.players.all_roles') }}</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" {{ (string) $roleFilter === (string) $r->id ? 'selected' : '' }}>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">{{ __('messages.players.status') }}</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">{{ __('messages.players.all_statuses') }}</option>
                            <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>{{ __('messages.players.active') }}</option>
                            <option value="banned" {{ $statusFilter === 'banned' ? 'selected' : '' }}>{{ __('messages.players.banned') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary btn-icon flex-grow-1">
                            <i class="bi bi-funnel"></i>
                            {{ __('messages.common.filter') }}
                        </button>
                        <a href="{{ route('admin.players') }}" class="btn btn-outline-secondary btn-square" title="{{ __('messages.common.reset') }}">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                @if($players->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-controller"></i>
                        <span>{{ __('messages.players.no_players') }}</span>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.players.username') }}</th>
                                    <th>{{ __('messages.players.role') }}</th>
                                    <th>{{ __('messages.players.money') }}</th>
                                    <th>{{ __('messages.players.status') }}</th>
                                    <th>{{ __('messages.players.last_seen') }}</th>
                                    <th class="text-end">{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($players as $player)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $player->username }}</div>
                                            <div class="text-muted small font-monospace">{{ $player->uuid ?: '—' }}</div>
                                        </td>
                                        <td>
                                            @php $role = $player->role ?? $defaultRole; @endphp
                                            @if($role)
                                                <span class="badge" style="background-color: {{ $role->color }};">{{ $role->name }}</span>
                                            @endif
                                        </td>
                                        <td><strong>{{ number_format($player->money) }}</strong></td>
                                        <td>
                                            @if($player->isCurrentlyBanned())
                                                <span class="badge text-bg-danger">{{ __('messages.players.banned') }}</span>
                                                @if($player->ban_reason)
                                                    <div class="text-danger small mt-1">{{ $player->ban_reason }}</div>
                                                @endif
                                                @if($player->banned_until)
                                                    <div class="text-muted small">
                                                        <i class="bi bi-clock me-1"></i>{{ __('messages.players.banned_until', ['date' => $player->banned_until->format('Y-m-d H:i')]) }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="badge text-bg-success">{{ __('messages.players.active') }}</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted">
                                            {{ $player->last_seen_at ? $player->last_seen_at->format('Y-m-d H:i') : __('messages.players.never_seen') }}
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-2">
                                                <form action="{{ route('admin.players.toggleBan', $player) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-sm {{ $player->is_banned ? 'btn-outline-success' : 'btn-outline-warning' }} btn-icon">
                                                        <i class="bi {{ $player->is_banned ? 'bi-unlock' : 'bi-slash-circle' }}"></i>
                                                        {{ $player->is_banned ? __('messages.players.unban') : __('messages.players.ban') }}
                                                    </button>
                                                </form>

                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-square"
                                                        data-bs-toggle="modal" data-bs-target="#editPlayerModal_{{ $player->id }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <form action="{{ route('admin.players.destroy', $player) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-square"
                                                            data-confirm="{{ __('messages.players.confirm_delete') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- TAB 2: EXTERNAL API MANAGEMENT --}}
    <div class="tab-pane fade {{ $currentTab === 'api' ? 'show active' : '' }}" id="tab-api" role="tabpanel">
        @if(session('new_api_token'))
            <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>
                        <div class="fw-semibold">{{ session('new_api_token_name') }}</div>
                        <div class="small">{{ __('messages.players.api_new_token_warning') }}</div>
                    </div>
                </div>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="newApiTokenInput"
                           value="{{ session('new_api_token') }}" readonly>
                    <button type="button" class="btn btn-dark btn-copy-snippet" data-copy-target="#newApiTokenInput">
                        <i class="bi bi-clipboard me-1"></i>{{ __('messages.players.api_copy') }}
                    </button>
                </div>
            </div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h2 class="h5 mb-1"><i class="bi bi-plug me-2"></i>{{ __('messages.players.api_title') }}</h2>
                    <p class="text-muted small mb-0">{{ __('messages.players.api_desc') }}</p>
                </div>
                <form action="{{ route('admin.players.api.toggle') }}" method="POST" class="d-flex align-items-center gap-3">
                    @csrf
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="player_api_enabled" value="0">
                        <input type="checkbox" class="form-check-input" id="player_api_enabled"
                               name="player_api_enabled" value="1"
                               {{ $playerApiEnabled ? 'checked' : '' }}
                               onchange="this.form.submit()">
                        <label class="form-check-label fw-semibold" for="player_api_enabled">
                            {{ __('messages.players.api_enable_label') }}
                        </label>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0"><i class="bi bi-plus-circle me-2"></i>{{ __('messages.players.api_create_token') }}</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.players.api.tokens.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="token_name" class="form-label">{{ __('messages.players.api_token_name') }}</label>
                                <input type="text" class="form-control" id="token_name" name="name"
                                       placeholder="{{ __('messages.players.api_token_name_placeholder') }}"
                                       value="{{ old('name') }}" required maxlength="100">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">{{ __('messages.players.api_token_abilities') }}</label>
                                @foreach($availableAbilities as $ability)
                                    @php
                                        $abilityKey = 'messages.players.api_ability_' . str_replace(':', '_', $ability);
                                    @endphp
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" name="abilities[]"
                                               value="{{ $ability }}" id="ability_{{ str_replace(':', '_', $ability) }}"
                                               checked>
                                        <label class="form-check-label" for="ability_{{ str_replace(':', '_', $ability) }}">
                                            {{ __($abilityKey) }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mb-3">
                                <label for="token_expires_at" class="form-label">{{ __('messages.players.api_token_expires') }}</label>
                                <input type="datetime-local" class="form-control" id="token_expires_at" name="expires_at"
                                       value="{{ old('expires_at') }}">
                                <div class="form-text">{{ __('messages.players.api_token_expires_hint') }}</div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-icon w-100">
                                <i class="bi bi-key"></i>
                                {{ __('messages.players.api_create_token') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0"><i class="bi bi-shield-lock me-2"></i>{{ __('messages.players.api_tokens_list') }}</h3>
                    </div>
                    <div class="card-body">
                        @if($apiTokens->isEmpty())
                            <div class="empty-state">
                                <i class="bi bi-key"></i>
                                <span>{{ __('messages.players.api_no_tokens') }}</span>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>{{ __('messages.players.api_token_name') }}</th>
                                            <th>{{ __('messages.players.api_token_abilities') }}</th>
                                            <th>{{ __('messages.players.api_last_used') }}</th>
                                            <th class="text-end">{{ __('messages.common.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($apiTokens as $token)
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">{{ $token->name }}</div>
                                                    <div class="small text-muted font-monospace">{{ $token->token_prefix }}••••••••</div>
                                                    @if($token->expires_at)
                                                        <div class="small {{ $token->isExpired() ? 'text-danger' : 'text-muted' }}">
                                                            {{ $token->isExpired() ? __('messages.players.api_expired') : __('messages.players.api_expires_at') }}:
                                                            {{ $token->expires_at->format('Y-m-d H:i') }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach(($token->abilities ?? []) as $ab)
                                                            <span class="badge text-bg-secondary font-monospace">{{ $ab }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                                <td class="small text-muted">
                                                    {{ $token->last_used_at ? $token->last_used_at->format('Y-m-d H:i') : __('messages.players.api_never_used') }}
                                                </td>
                                                <td class="text-end">
                                                    <form action="{{ route('admin.players.api.tokens.destroy', $token) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                                                                data-confirm="{{ __('messages.players.api_confirm_revoke') }}">
                                                            <i class="bi bi-trash"></i>
                                                            {{ __('messages.players.api_revoke') }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 3: INTERACTIVE API DOCUMENTATION --}}
    <div class="tab-pane fade {{ $currentTab === 'docs' ? 'show active' : '' }}" id="tab-docs" role="tabpanel">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="bi bi-book me-2"></i>{{ __('messages.players.api_docs_title') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="panel-muted-surface p-3 h-100">
                            <div class="small text-muted mb-1">{{ __('messages.players.api_docs_base_url') }}</div>
                            <code class="fs-6">{{ $apiBaseUrl }}</code>
                            <div class="small text-muted mt-2">{{ __('messages.players.api_docs_identifier_hint') }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="panel-muted-surface p-3 h-100">
                            <div class="fw-semibold mb-1">{{ __('messages.players.api_docs_auth_title') }}</div>
                            <p class="small text-muted mb-2">{{ __('messages.players.api_docs_auth_desc') }}</p>
                            <code class="d-block small">Authorization: Bearer &lt;YOUR_TOKEN&gt;</code>
                            <code class="d-block small">Content-Type: application/json</code>
                        </div>
                    </div>
                </div>

                @php
                    $endpoints = [
                        [
                            'id' => 'ban',
                            'method' => 'POST',
                            'badge' => 'text-bg-danger',
                            'path' => '/{uuid_or_username}/ban',
                            'ability' => 'players:ban',
                            'desc' => __('messages.players.api_docs_ep_ban_desc'),
                            'payload' => "{\n  \"reason\": \"Rule violation\",\n  \"duration\": 3600\n}",
                            'fields' => [
                                ['reason', __('messages.players.api_docs_ep_ban_reason_type'), __('messages.players.api_docs_ep_ban_reason_desc')],
                                ['duration', __('messages.players.api_docs_ep_ban_duration_type'), __('messages.players.api_docs_ep_ban_duration_desc')],
                                ['expires_at', __('messages.players.api_docs_ep_ban_expires_type'), __('messages.players.api_docs_ep_ban_expires_desc')],
                            ],
                            'curl' => "curl -X POST \"{$apiBaseUrl}/Notch/ban\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"reason\":\"Cheating detected\",\"duration\":3600}'",
                        ],
                        [
                            'id' => 'unban',
                            'method' => 'POST',
                            'badge' => 'text-bg-success',
                            'path' => '/{uuid_or_username}/unban',
                            'ability' => 'players:unban',
                            'desc' => __('messages.players.api_docs_ep_unban_desc'),
                            'payload' => '{}',
                            'fields' => [],
                            'curl' => "curl -X POST \"{$apiBaseUrl}/Notch/unban\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\"",
                        ],
                        [
                            'id' => 'role',
                            'method' => 'PATCH',
                            'badge' => 'text-bg-primary',
                            'path' => '/{uuid_or_username}/role',
                            'ability' => 'players:role',
                            'desc' => __('messages.players.api_docs_ep_role_desc'),
                            'payload' => "{\n  \"role\": \"VIP\"\n}",
                            'fields' => [
                                ['role_id', 'integer|null', __('messages.players.api_docs_ep_role_id_desc')],
                                ['role', 'string|null', __('messages.players.api_docs_ep_role_name_desc')],
                            ],
                            'curl' => "curl -X PATCH \"{$apiBaseUrl}/Notch/role\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"role\":\"VIP\"}'",
                        ],
                        [
                            'id' => 'points',
                            'method' => 'PATCH',
                            'badge' => 'text-bg-warning',
                            'path' => '/{uuid_or_username}/points',
                            'ability' => 'players:points',
                            'desc' => __('messages.players.api_docs_ep_points_desc'),
                            'payload' => "{\n  \"action\": \"add\",\n  \"amount\": 100\n}",
                            'fields' => [
                                ['action', __('messages.players.api_docs_ep_points_action_type'), __('messages.players.api_docs_ep_points_action_desc')],
                                ['amount', __('messages.players.api_docs_ep_points_amount_type'), __('messages.players.api_docs_ep_points_amount_desc')],
                            ],
                            'curl' => "curl -X PATCH \"{$apiBaseUrl}/Notch/points\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"action\":\"add\",\"amount\":100}'",
                        ],
                    ];
                @endphp

                <div class="accordion mb-4" id="apiDocsAccordion">
                    @foreach($endpoints as $ep)
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse_{{ $ep['id'] }}">
                                    <span class="badge {{ $ep['badge'] }} me-2">{{ $ep['method'] }}</span>
                                    <code class="me-3">/api/v1/players{{ $ep['path'] }}</code>
                                    <span class="badge text-bg-secondary font-monospace ms-auto me-2">{{ $ep['ability'] }}</span>
                                </button>
                            </h3>
                            <div id="collapse_{{ $ep['id'] }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                 data-bs-parent="#apiDocsAccordion">
                                <div class="accordion-body">
                                    <p class="mb-3">{{ $ep['desc'] }}</p>

                                    @if(!empty($ep['fields']))
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('messages.players.api_docs_field') }}</th>
                                                        <th>{{ __('messages.players.api_docs_type') }}</th>
                                                        <th>{{ __('messages.players.api_docs_description') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($ep['fields'] as $field)
                                                        <tr>
                                                            <td><code>{{ $field[0] }}</code></td>
                                                            <td class="small">{{ $field[1] }}</td>
                                                            <td class="small">{{ $field[2] }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <div class="small fw-semibold mb-1">{{ __('messages.players.api_docs_payload_json') }}</div>
                                            <pre class="bg-dark text-light p-3 rounded small mb-0"><code>{{ $ep['payload'] }}</code></pre>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-semibold">{{ __('messages.players.api_docs_curl_example') }}</span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-copy-snippet"
                                                        data-copy-target="#curl_{{ $ep['id'] }}">
                                                    <i class="bi bi-clipboard me-1"></i>{{ __('messages.players.api_copy') }}
                                                </button>
                                            </div>
                                            <pre class="bg-dark text-light p-3 rounded small mb-0"><code id="curl_{{ $ep['id'] }}">{{ $ep['curl'] }}</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <h3 class="h6 fw-semibold mb-2">{{ __('messages.players.api_docs_codes_title') }}</h3>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('messages.players.api_docs_code') }}</th>
                                <th>{{ __('messages.players.api_docs_meaning') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><span class="badge text-bg-success">200</span></td><td class="small">{{ __('messages.players.api_docs_code_200') }}</td></tr>
                            <tr><td><span class="badge text-bg-danger">401</span></td><td class="small">{{ __('messages.players.api_docs_code_401') }}</td></tr>
                            <tr><td><span class="badge text-bg-danger">403</span></td><td class="small">{{ __('messages.players.api_docs_code_403') }}</td></tr>
                            <tr><td><span class="badge text-bg-warning">404</span></td><td class="small">{{ __('messages.players.api_docs_code_404') }}</td></tr>
                            <tr><td><span class="badge text-bg-warning">422</span></td><td class="small">{{ __('messages.players.api_docs_code_422') }}</td></tr>
                            <tr><td><span class="badge text-bg-secondary">503</span></td><td class="small">{{ __('messages.players.api_docs_code_503') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / Pre-register Player Modal --}}
<div class="modal fade" id="addPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.players.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('messages.players.add_player') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>{{ __('messages.players.pre_register_hint') }}
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.players.username') }}</label>
                    <input type="text" name="username" class="form-control" required placeholder="Notch">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.players.uuid') }}</label>
                    <input type="text" name="uuid" class="form-control font-monospace" placeholder="{{ __('messages.players.uuid_placeholder') }}">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">{{ __('messages.players.role') }}</label>
                        <select name="role_id" class="form-select">
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}" {{ $r->is_default ? 'selected' : '' }}>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('messages.players.money') }}</label>
                        <input type="number" name="money" class="form-control" min="0" value="0" required>
                    </div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="is_banned" value="0">
                    <input type="checkbox" name="is_banned" value="1" class="form-check-input" id="addPlayerBanned">
                    <label class="form-check-label" for="addPlayerBanned">{{ __('messages.players.banned') }}</label>
                </div>
                <div>
                    <label class="form-label">{{ __('messages.players.ban_reason') }}</label>
                    <input type="text" name="ban_reason" class="form-control" placeholder="{{ __('messages.players.ban_reason_placeholder') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('messages.common.cancel') }}</button>
                <button type="submit" class="btn btn-primary btn-icon">
                    <i class="bi bi-save"></i>
                    {{ __('messages.common.save') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Player Modals --}}
@foreach($players as $player)
    <div class="modal fade" id="editPlayerModal_{{ $player->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.players.update', $player) }}" method="POST" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('messages.players.edit_player', ['name' => $player->username]) }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.common.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.players.username') }}</label>
                        <input type="text" name="username" class="form-control" value="{{ $player->username }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.players.uuid') }}</label>
                        <input type="text" name="uuid" class="form-control font-monospace" value="{{ $player->uuid }}">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('messages.players.role') }}</label>
                            <select name="role_id" class="form-select">
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}" {{ (int) $player->role_id === (int) $r->id ? 'selected' : '' }}>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('messages.players.money') }}</label>
                            <input type="number" name="money" class="form-control" min="0" value="{{ $player->money }}" required>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_banned" value="0">
                        <input type="checkbox" name="is_banned" value="1" class="form-check-input" id="editPlayerBanned_{{ $player->id }}"
                               {{ $player->is_banned ? 'checked' : '' }}>
                        <label class="form-check-label" for="editPlayerBanned_{{ $player->id }}">{{ __('messages.players.banned') }}</label>
                    </div>
                    <div>
                        <label class="form-label">{{ __('messages.players.ban_reason') }}</label>
                        <input type="text" name="ban_reason" class="form-control" value="{{ $player->ban_reason }}"
                               placeholder="{{ __('messages.players.ban_reason_placeholder') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('messages.common.cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-icon">
                        <i class="bi bi-save"></i>
                        {{ __('messages.common.update') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const copiedLabel = @json(__('messages.players.api_copied'));
    const copyLabel = @json(__('messages.players.api_copy'));

    document.querySelectorAll('.btn-copy-snippet').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetSelector = btn.getAttribute('data-copy-target');
            const target = targetSelector ? document.querySelector(targetSelector) : null;
            if (!target) return;

            const text = target.value !== undefined ? target.value : target.textContent;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.innerHTML = '<i class="bi bi-check2 me-1"></i>' + copiedLabel;
                    setTimeout(function () {
                        btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>' + copyLabel;
                    }, 1800);
                });
            }
        });
    });
});
</script>
@endpush
