@extends('layouts.admin')

@section('title', __('messages.server.title'))

@section('content')
<x-admin.page-header :title="__('messages.server.list_title')" icon="bi-hdd-network">
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#addServerModal">
            <i class="bi bi-plus-circle"></i>
            {{ __('messages.server.add_btn') }}
        </button>

        @if(in_array(($options?->auth_mode ?? 'azuriom') ?: 'azuriom', ['azuriom', 'both'], true) && !empty($options?->azuriom_url))
            <form method="POST" action="{{ route('admin.server.sync') }}" data-loading-label="{{ __('messages.common.loading') }}">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-icon">
                    <i class="bi bi-arrow-repeat"></i>
                    {{ __('messages.server.sync_btn') }}
                </button>
            </form>
        @endif
    </div>
</x-admin.page-header>

@if($error)
    <div class="alert alert-danger d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $error }}
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header">
        <h2 class="h5 mb-0">{{ __('messages.server.synced_servers') }}</h2>
    </div>
    <div class="card-body">
        @if(empty($servers))
            <div class="empty-state">
                <i class="bi bi-hdd-network"></i>
                <span>{{ __('messages.server.no_servers') }}</span>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('messages.server.name') }}</th>
                            <th>{{ __('messages.server.address') }}</th>
                            <th>{{ __('messages.server.port') }}</th>
                            <th>{{ __('messages.server.type') }}</th>
                            <th>{{ __('messages.server.source') }}</th>
                            <th>{{ __('messages.server.icon') }}</th>
                            <th class="text-end">{{ __('messages.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($servers as $server)
                            <tr>
                                <td><strong>{{ $server['name'] }}</strong></td>
                                <td><code>{{ $server['address'] }}</code></td>
                                <td>{{ $server['port'] }}</td>
                                <td><span class="badge text-bg-info">{{ $server['type'] }}</span></td>
                                <td>
                                    @if($server['is_manual'] ?? false)
                                        <span class="badge text-bg-secondary">{{ __('messages.server.source_manual') }}</span>
                                    @else
                                        <span class="badge text-bg-primary">{{ __('messages.server.source_azuriom') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($server['icon_url'])
                                            <img src="{{ $server['icon_url'] }}"
                                                 alt="{{ __('messages.server.icon') }}"
                                                 class="rounded"
                                                 width="40"
                                                 height="40"
                                                 style="object-fit: cover;">
                                            @if($server['icon_local'])
                                                <span class="badge text-bg-success" title="{{ __('messages.server.icon_local') }}">
                                                    <i class="bi bi-device-hdd"></i>
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-secondary">{{ __('messages.common.none') }}</span>
                                        @endif

                                        <form action="{{ route('admin.server.updateIcon', $server['pk']) }}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <label class="btn btn-sm btn-outline-secondary btn-square" title="{{ __('messages.server.upload_icon') }}">
                                                <i class="bi bi-upload"></i>
                                                <input type="file" name="icon" class="d-none" accept="image/*" onchange="this.form.submit()">
                                            </label>
                                        </form>

                                        @if($server['icon_local'])
                                            <form action="{{ route('admin.server.deleteIcon', $server['pk']) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-square" title="{{ __('messages.server.delete_icon') }}" data-confirm="{{ __('messages.server.confirm_delete_icon') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        @if(!($defaultServers[$server['id']] ?? false))
                                            <form method="POST"
                                                  action="{{ route('admin.server.set-default') }}"
                                                  class="d-inline"
                                                  data-loading-label="{{ __('messages.server.processing') }}">
                                                @csrf
                                                <input type="hidden" name="server_id" value="{{ $server['pk'] }}">
                                                <button type="submit" class="btn btn-sm btn-primary btn-icon" data-confirm="{{ __('messages.server.confirm_default') }}">
                                                    <i class="bi bi-star"></i>
                                                    {{ __('messages.server.set_default') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="badge text-bg-success">
                                                <i class="bi bi-check-circle-fill me-1"></i>{{ __('messages.server.is_default') }}
                                            </span>
                                        @endif

                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-square"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editServerModal_{{ $server['pk'] }}"
                                                title="{{ __('messages.common.edit') }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <form action="{{ route('admin.server.destroy', $server['pk']) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-danger btn-square"
                                                    title="{{ __('messages.common.delete') }}"
                                                    data-confirm="{{ __('messages.server.confirm_delete_server') }}">
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

            <div class="alert alert-info mt-3 mb-0">
                <i class="bi bi-info-circle me-2"></i>
                {{ __('messages.server.default_info') }}
            </div>
        @endif
    </div>
</div>

{{-- Add Manual Server Modal --}}
<div class="modal fade" id="addServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.server.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('messages.server.add_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.server.name') }}</label>
                    <input type="text" name="server_name" class="form-control" required placeholder="CentralCorp Survie">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label">{{ __('messages.server.address') }}</label>
                        <input type="text" name="server_ip" class="form-control" required placeholder="play.centralcorp.fr">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('messages.server.port') }}</label>
                        <input type="text" name="server_port" class="form-control" value="25565" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('messages.server.icon') }}</label>
                    <input type="file" name="icon" class="form-control" accept="image/*">
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" class="form-check-input" id="addServerDefault">
                    <label class="form-check-label" for="addServerDefault">{{ __('messages.server.set_default') }}</label>
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

{{-- Edit Server Modals --}}
@foreach($servers as $server)
    <div class="modal fade" id="editServerModal_{{ $server['pk'] }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.server.updateServer', $server['pk']) }}" method="POST" enctype="multipart/form-data" class="modal-content">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('messages.server.edit_modal_title') }} — {{ $server['name'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.common.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.server.name') }}</label>
                        <input type="text" name="server_name" class="form-control" value="{{ $server['name'] }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('messages.server.address') }}</label>
                            <input type="text" name="server_ip" class="form-control" value="{{ $server['address'] }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('messages.server.port') }}</label>
                            <input type="text" name="server_port" class="form-control" value="{{ $server['port'] }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.server.icon') }}</label>
                        <input type="file" name="icon" class="form-control" accept="image/*">
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
