@extends('layouts.admin')

@section('title', __('messages.instances.edit_title', ['name' => $instance->name]))

@section('content')
<x-admin.page-header :title="__('messages.instances.edit_title', ['name' => $instance->name])" icon="bi-pencil">
    <a href="{{ route('admin.instances') }}" class="btn btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
        {{ __('messages.common.back') }}
    </a>
</x-admin.page-header>

<form action="{{ route('admin.instances.update', $instance) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.instances._form', ['instance' => $instance])
</form>
@endsection
