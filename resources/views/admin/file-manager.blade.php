@extends('layouts.admin')

@section('title', __('messages.sidebar.file_manager'))
@section('page-title', __('messages.sidebar.file_manager'))

@section('content')
@if(isset($activeInstance))
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <i class="bi bi-layers me-2"></i>
            {{ __('messages.instances.file_manager_banner', ['name' => $activeInstance->name, 'dir' => 'storage/app/public/' . $activeInstance->resolvedDataDir()]) }}
        </div>
        <a href="{{ route('admin.instances') }}" class="btn btn-sm btn-outline-primary btn-icon">
            <i class="bi bi-gear"></i>
            {{ __('messages.sidebar.manage_instances') }}
        </a>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div id="fm" style="height: min(760px, calc(100vh - 250px));"></div>
    </div>
</div>
@endsection
