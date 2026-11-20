@php
    $isEdit = isset($instance);
    $bypassRoles = old('maintenance_bypass_roles', $instance->maintenance_bypass_roles ?? []);
    $extraBypassRoles = array_values(array_diff($bypassRoles, $availableRoles ?? []));
    $bypassUsersText = old('maintenance_bypass_users_text', implode(', ', $instance->maintenance_bypass_users ?? []));
    $jvmArgsText = old('jvm_args_text', is_array($instance->jvm_args ?? null) ? implode("\n", $instance->jvm_args) : '');
    $gameArgsText = old('game_args_text', is_array($instance->game_args ?? null) ? implode("\n", $instance->game_args) : '');
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.sidebar.general') }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('messages.instances.name') }}</label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="{{ old('name', $instance->name ?? '') }}" required placeholder="{{ __('messages.instances.name_placeholder') }}">
                    </div>

                    <div class="col-md-6">
                        <label for="slug" class="form-label">{{ __('messages.instances.slug') }}</label>
                        <input type="text" class="form-control font-monospace" id="slug" name="slug"
                               value="{{ old('slug', $instance->slug ?? '') }}" placeholder="{{ __('messages.instances.slug_placeholder') }}">
                        <div class="form-text">{{ __('messages.instances.slug_help') }}</div>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">{{ __('messages.instances.description') }}</label>
                        <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $instance->description ?? '') }}</textarea>
                    </div>

                    <div class="col-md-6">
                        <label for="icon" class="form-label">{{ __('messages.instances.icon') }}</label>
                        <input type="file" class="form-control" id="icon" name="icon" accept="image/*">
                        @if($isEdit && $instance->icon_path)
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <img src="{{ asset('storage/' . $instance->icon_path) }}" alt="" width="36" height="36" class="rounded">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="remove_icon" value="1" id="remove_icon">
                                    <label class="form-check-label small" for="remove_icon">{{ __('messages.instances.remove_icon') }}</label>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label for="background" class="form-label">{{ __('messages.instances.background') }}</label>
                        <input type="file" class="form-control" id="background" name="background" accept="image/*">
                        @if($isEdit && $instance->background_path)
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <img src="{{ asset('storage/' . $instance->background_path) }}" alt="" width="64" height="36" class="rounded" style="object-fit: cover;">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="remove_background" value="1" id="remove_background">
                                    <label class="form-check-label small" for="remove_background">{{ __('messages.instances.remove_background') }}</label>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($isEdit)
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">{{ __('messages.instances.minecraft_section') }}</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="minecraft_version" class="form-label">{{ __('messages.instances.minecraft_version') }}</label>
                            <input type="text" class="form-control" id="minecraft_version" name="minecraft_version"
                                   value="{{ old('minecraft_version', $instance->minecraft_version ?? '1.20.1') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="loader_type" class="form-label">{{ __('messages.instances.loader_type') }}</label>
                            @php($selectedLoaderType = strtolower((string) old('loader_type', $instance->loader_type ?? 'forge')))
                            <select class="form-select" id="loader_type" name="loader_type">
                                @foreach(['forge' => 'Forge', 'fabric' => 'Fabric', 'legacyfabric' => 'LegacyFabric', 'neoForge' => 'NeoForge', 'quilt' => 'Quilt'] as $type => $label)
                                    <option value="{{ $type }}" {{ $selectedLoaderType === strtolower($type) ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="loader_build_version" class="form-label">{{ __('messages.instances.loader_build') }}</label>
                            <input type="text" class="form-control" id="loader_build_version" name="loader_build_version"
                                   value="{{ old('loader_build_version', $instance->loader_build_version ?? '') }}"
                                   placeholder="ex: 47.2.0 / 0.15.11">
                        </div>

                        <div class="col-md-4">
                            <label for="min_ram" class="form-label">{{ __('messages.instances.min_ram') }}</label>
                            <input type="number" class="form-control" id="min_ram" name="min_ram" min="512" max="65536" step="128"
                                   value="{{ old('min_ram', $instance->min_ram ?? 2048) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="max_ram" class="form-label">{{ __('messages.instances.max_ram') }}</label>
                            <input type="number" class="form-control" id="max_ram" name="max_ram" min="512" max="65536" step="128"
                                   value="{{ old('max_ram', $instance->max_ram ?? 4096) }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="java_version" class="form-label">{{ __('messages.instances.java_version') }}</label>
                            <input type="number" class="form-control" id="java_version" name="java_version" min="8" max="30"
                                   value="{{ old('java_version', $instance->java_version ?? '') }}" placeholder="Auto (8 / 17 / 21)">
                        </div>

                        <div class="col-12">
                            <div class="panel-muted-surface p-3">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="loader_activation" value="0">
                                            <input type="checkbox" class="form-check-input" id="loader_activation" name="loader_activation" value="1"
                                                   {{ old('loader_activation', $instance->loader_activation ?? false) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="loader_activation">{{ __('messages.instances.loader_activation') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="mods_enabled" value="0">
                                            <input type="checkbox" class="form-check-input" id="mods_enabled" name="mods_enabled" value="1"
                                                   {{ old('mods_enabled', $instance->mods_enabled ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="mods_enabled">{{ __('messages.instances.mods_enabled') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="file_verification" value="0">
                                            <input type="checkbox" class="form-check-input" id="file_verification" name="file_verification" value="1"
                                                   {{ old('file_verification', $instance->file_verification ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="file_verification">{{ __('messages.instances.file_verification') }}</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="embedded_java" value="0">
                                            <input type="checkbox" class="form-check-input" id="embedded_java" name="embedded_java" value="1"
                                                   {{ old('embedded_java', $instance->embedded_java ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="embedded_java">{{ __('messages.instances.embedded_java') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="jvm_args_text" class="form-label">{{ __('messages.instances.jvm_args') }}</label>
                            <textarea class="form-control font-monospace" id="jvm_args_text" name="jvm_args_text" rows="3"
                                      placeholder="-XX:+UseG1GC">{{ $jvmArgsText }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="game_args_text" class="form-label">{{ __('messages.instances.game_args') }}</label>
                            <textarea class="form-control font-monospace" id="game_args_text" name="game_args_text" rows="3">{{ $gameArgsText }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.common.status') }}</h2>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="position" class="form-label">{{ __('messages.instances.position') }}</label>
                    <input type="number" class="form-control" id="position" name="position" min="0" max="9999"
                           value="{{ old('position', $instance->position ?? 0) }}">
                </div>

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" class="form-check-input" id="is_enabled" name="is_enabled" value="1"
                           {{ old('is_enabled', $instance->is_enabled ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_enabled">{{ __('messages.instances.is_enabled') }}</label>
                </div>

                <div class="form-check form-switch">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" class="form-check-input" id="is_default" name="is_default" value="1"
                           {{ old('is_default', $instance->is_default ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">{{ __('messages.instances.is_default') }}</label>
                </div>
            </div>
        </div>

        @if($isEdit)
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">{{ __('messages.instances.server_section') }}</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="server_id" class="form-label">{{ __('messages.instances.server') }}</label>
                        <select class="form-select" id="server_id" name="server_id">
                            <option value="">{{ __('messages.instances.no_server') }}</option>
                            @foreach($servers as $srv)
                                <option value="{{ $srv->id }}" {{ (string) old('server_id', $instance->server_id ?? '') === (string) $srv->id ? 'selected' : '' }}>
                                    {{ $srv->server_name }} ({{ $srv->server_ip }}:{{ $srv->server_port }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="auto_connect" value="0">
                        <input type="checkbox" class="form-check-input" id="auto_connect" name="auto_connect" value="1"
                               {{ old('auto_connect', $instance->auto_connect ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="auto_connect">{{ __('messages.instances.auto_connect') }}</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="whitelist_activate" value="0">
                        <input type="checkbox" class="form-check-input" id="whitelist_activate" name="whitelist_activate" value="1"
                               {{ old('whitelist_activate', $instance->whitelist_activate ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="whitelist_activate">{{ __('messages.instances.whitelist_activate') }}</label>
                    </div>

                    <hr>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="maintenance" value="0">
                        <input type="checkbox" class="form-check-input" id="maintenance" name="maintenance" value="1"
                               {{ old('maintenance', $instance->maintenance ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="maintenance">{{ __('messages.instances.maintenance') }}</label>
                    </div>

                    <div class="mb-3">
                        <label for="maintenance_message" class="form-label">{{ __('messages.instances.maintenance_message') }}</label>
                        <input type="text" class="form-control" id="maintenance_message" name="maintenance_message"
                               value="{{ old('maintenance_message', $instance->maintenance_message ?? '') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.instances.bypass_roles') }}</label>
                        @if(!empty($availableRoles))
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @foreach($availableRoles as $roleName)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="maintenance_bypass_roles[]"
                                               value="{{ $roleName }}" id="bypass_role_{{ $loop->index }}"
                                               {{ in_array($roleName, $bypassRoles, true) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="bypass_role_{{ $loop->index }}">{{ $roleName }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <input type="text" class="form-control form-control-sm" name="maintenance_bypass_roles_text"
                               value="{{ implode(', ', $extraBypassRoles) }}"
                               placeholder="{{ __('messages.security.bypass_roles_extra_placeholder') }}">
                    </div>

                    <div>
                        <label for="maintenance_bypass_users_text" class="form-label">{{ __('messages.instances.bypass_users') }}</label>
                        <input type="text" class="form-control form-control-sm" id="maintenance_bypass_users_text" name="maintenance_bypass_users_text"
                               value="{{ $bypassUsersText }}" placeholder="Steve, Alex">
                    </div>
                </div>
            </div>
        @endif

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-icon flex-grow-1">
                <i class="bi bi-save"></i>
                {{ __('messages.common.save') }}
            </button>
            <a href="{{ route('admin.instances') }}" class="btn btn-outline-secondary">
                {{ __('messages.common.cancel') }}
            </a>
        </div>
    </div>
</div>
