@extends('layouts.admin')

@section('title', __('messages.roles.title'))

@section('content')
<x-admin.page-header :title="__('messages.roles.header')" :subtitle="__('messages.roles.subtitle')" icon="bi-person-badge">
    <a href="{{ route('admin.bg') }}" class="btn btn-outline-primary btn-icon">
        <i class="bi bi-image"></i>
        {{ __('messages.sidebar.background') }}
    </a>
</x-admin.page-header>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.roles.add_role') }}</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.roles.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('messages.roles.name') }}</label>
                        <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required placeholder="VIP">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label for="color" class="form-label">{{ __('messages.roles.color') }}</label>
                            <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="{{ old('color', '#00bd7a') }}" required>
                        </div>
                        <div class="col-6">
                            <label for="power" class="form-label">{{ __('messages.roles.power') }}</label>
                            <input type="number" class="form-control" id="power" name="power" min="0" max="10000" value="{{ old('power', 10) }}" required>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" class="form-check-input" id="is_default" name="is_default" value="1">
                        <label class="form-check-label" for="is_default">{{ __('messages.roles.is_default') }}</label>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="bypass_maintenance" value="0">
                        <input type="checkbox" class="form-check-input" id="bypass_maintenance" name="bypass_maintenance" value="1">
                        <label class="form-check-label" for="bypass_maintenance">{{ __('messages.roles.bypass_maintenance') }}</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-icon w-100">
                        <i class="bi bi-plus-circle"></i>
                        {{ __('messages.common.add') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.roles.list_title') }}</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('messages.roles.name') }}</th>
                                <th>{{ __('messages.roles.power') }}</th>
                                <th>{{ __('messages.roles.players_count') }}</th>
                                <th>{{ __('messages.common.status') }}</th>
                                <th class="text-end">{{ __('messages.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $role)
                                <tr>
                                    <td>
                                        <span class="badge fs-6" style="background-color: {{ $role->color }};">{{ $role->name }}</span>
                                    </td>
                                    <td><code>{{ $role->power }}</code></td>
                                    <td>{{ $role->players_count }}</td>
                                    <td>
                                        @if($role->is_default)
                                            <span class="badge text-bg-primary">{{ __('messages.roles.default_badge') }}</span>
                                        @endif
                                        @if($role->bypass_maintenance)
                                            <span class="badge text-bg-warning">{{ __('messages.roles.bypass_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-square"
                                                    data-bs-toggle="modal" data-bs-target="#editRoleModal_{{ $role->id }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            @if($roles->count() > 1)
                                                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-square"
                                                            data-confirm="{{ __('messages.roles.confirm_delete') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@foreach($roles as $role)
    <div class="modal fade" id="editRoleModal_{{ $role->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.roles.update', $role) }}" method="POST" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('messages.roles.edit_role') }} — {{ $role->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.common.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.roles.name') }}</label>
                        <input type="text" class="form-control" name="name" value="{{ $role->name }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('messages.roles.color') }}</label>
                            <input type="color" class="form-control form-control-color w-100" name="color" value="{{ $role->color }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('messages.roles.power') }}</label>
                            <input type="number" class="form-control" name="power" min="0" max="10000" value="{{ $role->power }}" required>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" class="form-check-input" name="is_default" value="1" id="edit_def_{{ $role->id }}"
                               {{ $role->is_default ? 'checked' : '' }}>
                        <label class="form-check-label" for="edit_def_{{ $role->id }}">{{ __('messages.roles.is_default') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="bypass_maintenance" value="0">
                        <input type="checkbox" class="form-check-input" name="bypass_maintenance" value="1" id="edit_byp_{{ $role->id }}"
                               {{ $role->bypass_maintenance ? 'checked' : '' }}>
                        <label class="form-check-label" for="edit_byp_{{ $role->id }}">{{ __('messages.roles.bypass_maintenance') }}</label>
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
