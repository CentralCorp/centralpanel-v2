@extends('layouts.admin')

@section('title', __('messages.ignore.title'))
@section('page-title', __('messages.ignore.title'))

@section('content')
@if(isset($activeInstance))
    <div class="alert alert-info mb-3">
        <i class="bi bi-layers me-2"></i>
        {{ __('messages.ignore.active_instance_hint', ['name' => $activeInstance->name]) }}
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.ignore.add_header') }}</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.ignore.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="scope" class="form-label">{{ __('messages.ignore.scope_label') }}</label>
                        <select class="form-select" id="scope" name="scope">
                            <option value="instance">{{ __('messages.ignore.scope_instance', ['name' => $activeInstance->name ?? 'Default']) }}</option>
                            <option value="global">{{ __('messages.ignore.scope_global') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ignored_folders" class="form-label">{{ __('messages.ignore.paths_label') }}</label>
                        <input type="text" class="form-control" id="ignored_folders" name="ignored_folders" placeholder="{{ __('messages.ignore.paths_placeholder') }}">
                        <div class="form-text">{{ __('messages.ignore.paths_hint') }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-icon">
                        <i class="bi bi-plus-circle"></i>
                        {{ __('messages.common.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.ignore.current_header') }}</h2>
            </div>
            <div class="card-body">
                @if($folders->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-folder-x"></i>
                        <span>{{ __('messages.ignore.none_ignored') }}</span>
                    </div>
                @else
                    <div class="panel-list">
                        @foreach($folders as $folder)
                            <div class="panel-list-item">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-break font-monospace">{{ $folder->folder_name }}</span>
                                    @if($folder->instance_id === null)
                                        <span class="badge text-bg-primary">{{ __('messages.ignore.global_badge') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('messages.ignore.instance_badge') }}</span>
                                    @endif
                                </div>
                                <form action="{{ route('admin.ignore.destroyFolder', $folder->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-icon" data-confirm="{{ __('messages.common.confirm_delete') }}">
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
