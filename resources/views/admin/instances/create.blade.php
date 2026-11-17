@extends('layouts.admin')

@section('title', __('messages.instances.create_title'))

@section('content')
<x-admin.page-header :title="__('messages.instances.create_title')" icon="bi-plus-circle">
    <a href="{{ route('admin.instances') }}" class="btn btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
        {{ __('messages.common.back') }}
    </a>
</x-admin.page-header>

<form action="{{ route('admin.instances.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.instances._form')
</form>
@endsection
