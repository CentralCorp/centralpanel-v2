@extends('layouts.admin')

@section('title', __('messages.instances.title'))

@section('content')
<x-admin.page-header :title="__('messages.instances.header')" :subtitle="__('messages.instances.subtitle')" icon="bi-layers">
    <a href="{{ route('admin.instances.create') }}" class="btn btn-primary btn-icon">
        <i class="bi bi-plus-circle"></i>
        {{ __('messages.instances.create_btn') }}
    </a>
</x-admin.page-header>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('messages.instances.name') }}</th>
                        <th>{{ __('messages.instances.minecraft_version') }}</th>
                        <th>{{ __('messages.instances.server') }}</th>
                        <th>{{ __('messages.instances.data_dir') }}</th>
                        <th>{{ __('messages.common.status') }}</th>
                        <th class="text-end">{{ __('messages.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($instances as $inst)
                        @php $isActive = $activeInstance && $activeInstance->id === $inst->id; @endphp
                        <tr class="{{ $isActive ? 'table-active' : '' }}">
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    @if($inst->icon_path)
                                        <img src="{{ asset('storage/' . $inst->icon_path) }}" alt="{{ $inst->name }}"
                                             width="40" height="40" class="rounded" style="object-fit: cover;">
                                    @else
                                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-body-secondary" style="width: 40px; height: 40px;">
                                            <i class="bi bi-box fs-5"></i>
                                        </span>
                                    @endif
                                    <div>
                                        <div class="fw-semibold d-flex flex-wrap align-items-center gap-2">
                                            {{ $inst->name }}
                                            @if($inst->is_default)
                                                <span class="badge text-bg-success">{{ __('messages.instances.default_badge') }}</span>
                                            @endif
                                            @if($isActive)
                                                <span class="badge text-bg-primary">{{ __('messages.instances.active_badge') }}</span>
                                            @endif
                                            @if($inst->maintenance)
                                                <span class="badge text-bg-warning">{{ __('messages.instances.maintenance_badge') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-muted small font-monospace">{{ $inst->slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-bg-info">{{ $inst->minecraft_version }}</span>
                                @if($inst->loader_activation && $inst->loader_type)
                                    <span class="badge text-bg-secondary">{{ ucfirst($inst->loader_type) }} {{ $inst->loader_build_version }}</span>
                                @else
                                    <span class="badge text-bg-light text-dark border">Vanilla</span>
                                @endif
                            </td>
                            <td>
                                @if($inst->server)
                                    <span>{{ $inst->server->server_name }}</span>
                                    <div class="text-muted small"><code>{{ $inst->server->server_ip }}:{{ $inst->server->server_port }}</code></div>
                                @else
                                    <span class="text-muted small">{{ __('messages.common.none') }}</span>
                                @endif
                            </td>
                            <td>
                                <code class="small">storage/app/public/{{ $inst->resolvedDataDir() }}</code>
                            </td>
                            <td>
                                @if($inst->is_enabled)
                                    <span class="badge text-bg-success">{{ __('messages.common.enabled') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('messages.common.disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if(!$isActive)
                                        <form action="{{ route('admin.instances.switch') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="instance_id" value="{{ $inst->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary btn-icon">
                                                <i class="bi bi-check2-circle"></i>
                                                {{ __('messages.instances.switch_btn') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if(!$inst->is_default)
                                        <form action="{{ route('admin.instances.default', $inst) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success btn-square" title="{{ __('messages.instances.set_default_btn') }}">
                                                <i class="bi bi-star"></i>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.instances.edit', $inst) }}" class="btn btn-sm btn-outline-secondary btn-square" title="{{ __('messages.common.edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    @if($instances->count() > 1)
                                        <form action="{{ route('admin.instances.destroy', $inst) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-square"
                                                    title="{{ __('messages.common.delete') }}"
                                                    data-confirm="{{ __('messages.instances.confirm_delete') }}">
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
@endsection
