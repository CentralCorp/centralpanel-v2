@extends('layouts.admin')

@section('title', __('messages.players.title'))

@section('content')
<x-admin.page-header :title="__('messages.players.header')" :subtitle="__('messages.players.subtitle')" icon="bi-controller">
    <button type="button" class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addPlayerModal">
        <i class="bi bi-person-plus"></i>
        {{ __('messages.players.add_player') }}
    </button>
</x-admin.page-header>

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
                                    @if($player->is_banned)
                                        <span class="badge text-bg-danger">{{ __('messages.players.banned') }}</span>
                                        @if($player->ban_reason)
                                            <div class="text-danger small mt-1">{{ $player->ban_reason }}</div>
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
