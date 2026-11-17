@extends('layouts.admin')

@section('title', __('messages.security.title'))
@section('page-title', __('messages.security.header'))

@section('content')
@php
    $globalBypassRoles = $securityOptions->maintenance_bypass_roles ?? [];
    $globalExtraRoles = array_values(array_diff($globalBypassRoles, $availableRoles ?? []));
    $globalBypassUsers = implode(', ', $securityOptions->maintenance_bypass_users ?? []);

    $instanceBypassRoles = $activeInstance->maintenance_bypass_roles ?? [];
    $instanceExtraRoles = array_values(array_diff($instanceBypassRoles, $availableRoles ?? []));
    $instanceBypassUsers = implode(', ', $activeInstance->maintenance_bypass_users ?? []);
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="bi bi-shield-lock me-2"></i>{{ __('messages.security.global_section') }}
                </h2>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.security.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="scope" value="global">
                    <input type="hidden" name="bypass_submitted" value="1">

                    <div class="panel-muted-surface p-3 mb-4">
                        <div class="form-check form-switch">
                            <input type="hidden" name="maintenance" value="0">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="maintenance"
                                   name="maintenance"
                                   value="1"
                                   {{ old('maintenance', $securityOptions->maintenance) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="maintenance">
                                {{ __('messages.security.maintenance_enable') }}
                            </label>
                            <div class="form-text">{{ __('messages.security.maintenance_desc') }}</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="maintenance_message" class="form-label">{{ __('messages.security.maintenance_msg') }}</label>
                        <input type="text"
                               class="form-control"
                               id="maintenance_message"
                               name="maintenance_message"
                               value="{{ old('maintenance_message', $securityOptions->maintenance_message) }}"
                               required
                               placeholder="{{ __('messages.security.maintenance_placeholder') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.security.bypass_roles_label') }}</label>
                        @if(!empty($availableRoles))
                            <div class="d-flex flex-wrap gap-3 mb-2">
                                @foreach($availableRoles as $roleName)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="maintenance_bypass_roles[]"
                                               value="{{ $roleName }}" id="global_role_{{ $loop->index }}"
                                               {{ in_array($roleName, $globalBypassRoles, true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="global_role_{{ $loop->index }}">{{ $roleName }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <input type="text" class="form-control form-control-sm" name="maintenance_bypass_roles_text"
                               value="{{ implode(', ', $globalExtraRoles) }}"
                               placeholder="{{ __('messages.security.bypass_roles_extra_placeholder') }}">
                    </div>

                    <div class="mb-4">
                        <label for="maintenance_bypass_users_text" class="form-label">{{ __('messages.security.bypass_users_label') }}</label>
                        <input type="text" class="form-control" id="maintenance_bypass_users_text" name="maintenance_bypass_users_text"
                               value="{{ old('maintenance_bypass_users_text', $globalBypassUsers) }}"
                               placeholder="{{ __('messages.security.bypass_users_placeholder') }}">
                        <div class="form-text">{{ __('messages.security.bypass_users_hint') }}</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-icon">
                        <i class="bi bi-save"></i>
                        {{ __('messages.common.update') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if(isset($activeInstance))
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-layers me-2"></i>{{ __('messages.security.instance_section', ['name' => $activeInstance->name]) }}
                    </h2>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.security.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="scope" value="instance">

                        <div class="panel-muted-surface p-3 mb-4">
                            <div class="form-check form-switch">
                                <input type="hidden" name="instance_maintenance" value="0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="instance_maintenance"
                                       name="instance_maintenance"
                                       value="1"
                                       {{ old('instance_maintenance', $activeInstance->maintenance) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="instance_maintenance">
                                    {{ __('messages.security.maintenance_enable') }}
                                </label>
                                <div class="form-text">{{ __('messages.security.instance_maintenance_desc') }}</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="instance_maintenance_message" class="form-label">{{ __('messages.security.maintenance_msg') }}</label>
                            <input type="text"
                                   class="form-control"
                                   id="instance_maintenance_message"
                                   name="instance_maintenance_message"
                                   value="{{ old('instance_maintenance_message', $activeInstance->maintenance_message) }}"
                                   placeholder="{{ __('messages.security.maintenance_placeholder') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.security.bypass_roles_label') }}</label>
                            @if(!empty($availableRoles))
                                <div class="d-flex flex-wrap gap-3 mb-2">
                                    @foreach($availableRoles as $roleName)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="instance_bypass_roles[]"
                                                   value="{{ $roleName }}" id="inst_role_{{ $loop->index }}"
                                                   {{ in_array($roleName, $instanceBypassRoles, true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="inst_role_{{ $loop->index }}">{{ $roleName }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <input type="text" class="form-control form-control-sm" name="instance_bypass_roles_text"
                                   value="{{ implode(', ', $instanceExtraRoles) }}"
                                   placeholder="{{ __('messages.security.bypass_roles_extra_placeholder') }}">
                        </div>

                        <div class="mb-4">
                            <label for="instance_bypass_users_text" class="form-label">{{ __('messages.security.bypass_users_label') }}</label>
                            <input type="text" class="form-control" id="instance_bypass_users_text" name="instance_bypass_users_text"
                                   value="{{ old('instance_bypass_users_text', $instanceBypassUsers) }}"
                                   placeholder="{{ __('messages.security.bypass_users_placeholder') }}">
                            <div class="form-text">{{ __('messages.security.bypass_users_hint') }}</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-save"></i>
                            {{ __('messages.common.update') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
