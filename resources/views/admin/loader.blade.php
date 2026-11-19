@extends('layouts.admin')

@section('title', __('messages.loader.title'))
@section('page-title', __('messages.loader.title'))

@section('content')
@if(isset($activeInstance))
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
        <span>
            <i class="bi bi-layers me-2"></i>
            {{ __('messages.loader.active_instance_hint', ['name' => $activeInstance->name, 'slug' => $activeInstance->slug]) }}
        </span>
        <a href="{{ route('admin.instances.edit', $activeInstance) }}" class="btn btn-sm btn-outline-primary btn-icon">
            <i class="bi bi-pencil"></i>
            {{ __('messages.common.edit') }}
        </a>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.loader.update') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="minecraft_version" class="form-label">{{ __('messages.loader.minecraft_version') }}</label>
                    <input type="text" class="form-control" id="minecraft_version" name="minecraft_version"
                           placeholder="ex: 1.20.1" value="{{ old('minecraft_version', $row->minecraft_version ?? '') }}" required>
                </div>

                <div class="col-md-6">
                    <label for="loader-type" class="form-label">{{ __('messages.loader.loader_type') }}</label>
                    @php($selectedLoaderType = strtolower((string) old('loader_type', $row->loader_type ?? '')))
                    <select class="form-select" id="loader-type" name="loader_type">
                        @foreach (['forge' => 'Forge', 'fabric' => 'Fabric', 'legacyfabric' => 'LegacyFabric', 'neoForge' => 'NeoForge', 'quilt' => 'Quilt'] as $type => $label)
                            <option value="{{ $type }}" {{ $selectedLoaderType === strtolower($type) ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <div class="panel-muted-surface p-3">
                        <div class="form-check form-switch">
                            <input type="hidden" name="loader_activation" value="0">
                            <input type="checkbox" class="form-check-input" id="loader-activation" name="loader_activation"
                                   value="1" {{ old('loader_activation', $row->loader_activation ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="loader-activation">{{ __('messages.loader.enable_loader') }}</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <label for="loader-build-version" class="form-label">{{ __('messages.loader.build_version') }}</label>
                    <select class="form-select" id="loader-build-version" name="loader_forge_version"></select>
                    <input type="text" class="form-control d-none" id="loader-build-version-input"
                           name="loader_build_version"
                           value="{{ old('loader_build_version', $row->loader_build_version ?? '') }}">
                </div>

                <div class="col-md-4">
                    <label for="java_version" class="form-label">{{ __('messages.loader.java_version') }}</label>
                    <input type="number" class="form-control" id="java_version" name="java_version" min="8" max="30"
                           placeholder="ex: 17, 21"
                           value="{{ old('java_version', $row->java_version ?? '') }}">
                    <div class="form-text">{{ __('messages.loader.java_version_help') }}</div>
                </div>

                <div class="col-md-6">
                    <label for="jvm_args_text" class="form-label">{{ __('messages.loader.jvm_args') }}</label>
                    <textarea class="form-control font-monospace" id="jvm_args_text" name="jvm_args_text" rows="3"
                              placeholder="-XX:+UseG1GC">{{ old('jvm_args_text', is_array($row->jvm_args ?? null) ? implode("\n", $row->jvm_args) : '') }}</textarea>
                    <div class="form-text">{{ __('messages.loader.jvm_args_help') }}</div>
                </div>

                <div class="col-md-6">
                    <label for="game_args_text" class="form-label">{{ __('messages.loader.game_args') }}</label>
                    <textarea class="form-control font-monospace" id="game_args_text" name="game_args_text" rows="3">{{ old('game_args_text', is_array($row->game_args ?? null) ? implode("\n", $row->game_args) : '') }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-icon mt-4">
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
        const loaderType = document.getElementById('loader-type');
        const mcVersion = document.getElementById('minecraft_version');
        const buildSelect = document.getElementById('loader-build-version');
        const buildInput = document.getElementById('loader-build-version-input');
        const currentVersion = "{{ $row->loader_forge_version ?? $row->loader_build_version ?? '' }}";

        function toggleBuildInputs(showSelect) {
            buildSelect.classList.toggle('d-none', !showSelect);
            buildInput.classList.toggle('d-none', showSelect);
        }

        function setLoading() {
            buildSelect.innerHTML = '<option>{{ __('messages.common.loading') }}</option>';
        }

        function syncBuildVersion() {
            if (!buildSelect.classList.contains('d-none')) {
                buildInput.value = buildSelect.value;
            }
        }

        function renderBuilds(builds, mapper) {
            if (!builds || builds.length === 0) {
                toggleBuildInputs(false);
                return;
            }
            buildSelect.innerHTML = '';
            builds.forEach((build) => {
                const value = mapper(build);
                const option = document.createElement('option');
                option.value = value;
                option.text = value;
                if (value === currentVersion) option.selected = true;
                buildSelect.appendChild(option);
            });
            toggleBuildInputs(true);
            syncBuildVersion();
        }

        function loadForgeBuilds(version) {
            if (!version) return;
            setLoading();
            fetch(`/admin/loader/builds?mc_version=${encodeURIComponent(version)}`)
                .then(response => response.json())
                .then(data => renderBuilds(data.builds || [], build => build))
                .catch(() => toggleBuildInputs(false));
        }

        function loadFabricVersions() {
            setLoading();
            fetch('/admin/loader/fabric-versions')
                .then(response => response.json())
                .then(data => renderBuilds(data.versions || [], version => version.version))
                .catch(() => toggleBuildInputs(false));
        }

        function refreshBuildSource() {
            switch (loaderType.value) {
                case 'forge':
                    loadForgeBuilds(mcVersion.value);
                    break;
                case 'fabric':
                    loadFabricVersions();
                    break;
                default:
                    toggleBuildInputs(false);
            }
        }

        loaderType.addEventListener('change', refreshBuildSource);
        mcVersion.addEventListener('input', () => {
            if (loaderType.value === 'forge') loadForgeBuilds(mcVersion.value);
        });
        buildSelect.addEventListener('change', syncBuildVersion);
        buildSelect.closest('form').addEventListener('submit', syncBuildVersion);

        refreshBuildSource();
    });
</script>
@endpush
