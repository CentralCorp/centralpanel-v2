@extends('layouts.admin')

@section('title', __('messages.config.title'))
@section('page-title', __('messages.config.header'))

@section('content')
@php
    $currentAuthMode = old('auth_mode', $options->auth_mode ?? 'azuriom');
    $currentNewsSource = old('news_source', $options->news_source ?? 'azuriom');
    $requiresAzuriom = in_array($currentAuthMode, ['azuriom', 'both'], true);
@endphp

<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">{{ __('messages.config.general_settings') }}</h2>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.config.update') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="app_name" class="form-label">{{ __('messages.config.app_name') }}</label>
                <input type="text" class="form-control" id="app_name" name="app_name"
                       value="{{ old('app_name', config('app.name')) }}" required>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h3 class="h6 fw-semibold mb-3">
                    <i class="bi bi-shield-check me-2"></i>{{ __('messages.config.auth_section') }}
                </h3>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="auth_mode" class="form-label">{{ __('messages.config.auth_mode') }}</label>
                        <select class="form-select" id="auth_mode" name="auth_mode">
                            <option value="azuriom" {{ $currentAuthMode === 'azuriom' ? 'selected' : '' }}>
                                {{ __('messages.config.auth_mode_azuriom') }}
                            </option>
                            <option value="microsoft" {{ $currentAuthMode === 'microsoft' ? 'selected' : '' }}>
                                {{ __('messages.config.auth_mode_microsoft') }}
                            </option>
                            <option value="both" {{ $currentAuthMode === 'both' ? 'selected' : '' }}>
                                {{ __('messages.config.auth_mode_both') }}
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="news_source" class="form-label">{{ __('messages.config.news_source') }}</label>
                        <select class="form-select" id="news_source" name="news_source">
                            <option value="azuriom" {{ $currentNewsSource === 'azuriom' ? 'selected' : '' }}>
                                {{ __('messages.config.news_source_azuriom') }}
                            </option>
                            <option value="panel" {{ $currentNewsSource === 'panel' ? 'selected' : '' }}>
                                {{ __('messages.config.news_source_panel') }}
                            </option>
                            <option value="both" {{ $currentNewsSource === 'both' ? 'selected' : '' }}>
                                {{ __('messages.config.news_source_both') }}
                            </option>
                        </select>
                    </div>

                    <div class="col-12" id="microsoftClientGroup">
                        <label for="microsoft_client_id" class="form-label">{{ __('messages.config.microsoft_client_id') }}</label>
                        <input type="text" class="form-control" id="microsoft_client_id" name="microsoft_client_id"
                               placeholder="{{ __('messages.config.microsoft_client_id_placeholder') }}"
                               value="{{ old('microsoft_client_id', $options->microsoft_client_id ?? '') }}">
                        <div class="form-text">{{ __('messages.config.microsoft_client_id_help') }}</div>
                    </div>
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4" id="azuriomConfigGroup">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-semibold mb-0">
                        <i class="bi bi-globe me-2"></i>{{ __('messages.config.azuriom_section') }}
                    </h3>
                    <span class="badge text-bg-secondary {{ $requiresAzuriom ? 'd-none' : '' }}" id="azuriomOptionalBadge">
                        {{ __('messages.config.azuriom_optional_hint') }}
                    </span>
                </div>

                <div class="mb-3">
                    <label for="azuriom_url" class="form-label">{{ __('messages.config.azuriom_url') }}</label>
                    <input type="url" class="form-control" id="azuriom_url" name="azuriom_url"
                           placeholder="https://votre-site.azuriom.com"
                           value="{{ old('azuriom_url', $options->azuriom_url ?? '') }}"
                           {{ $requiresAzuriom ? 'required' : '' }}>
                </div>
                <div>
                    <label for="azuriom_api_key" class="form-label">{{ __('messages.config.azuriom_api_key') }}</label>
                    <input type="text" class="form-control" id="azuriom_api_key" name="azuriom_api_key"
                           placeholder="Votre clé API Azuriom"
                           value="{{ old('azuriom_api_key', $options->azuriom_api_key ?? '') }}"
                           {{ $requiresAzuriom ? 'required' : '' }}>
                    <div class="form-text">{{ __('messages.config.api_key_desc') }}</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-icon">
                <i class="bi bi-save"></i>
                {{ __('messages.common.save') }}
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const authModeSelect = document.getElementById('auth_mode');
    const azuriomUrl = document.getElementById('azuriom_url');
    const azuriomApiKey = document.getElementById('azuriom_api_key');
    const optionalBadge = document.getElementById('azuriomOptionalBadge');

    function syncAuthRequirements() {
        const mode = authModeSelect.value;
        const requiresAzuriom = (mode === 'azuriom' || mode === 'both');
        azuriomUrl.required = requiresAzuriom;
        azuriomApiKey.required = requiresAzuriom;
        optionalBadge.classList.toggle('d-none', requiresAzuriom);
    }

    authModeSelect.addEventListener('change', syncAuthRequirements);
    syncAuthRequirements();
});
</script>
@endpush
