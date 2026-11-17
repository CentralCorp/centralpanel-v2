@extends('layouts.admin')

@section('title', __('messages.news.create_title'))

@section('content')
<x-admin.page-header :title="__('messages.news.create_title')" icon="bi-plus-circle">
    <a href="{{ route('admin.news') }}" class="btn btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
        {{ __('messages.common.back') }}
    </a>
</x-admin.page-header>

<form action="{{ route('admin.news.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.news._form')
</form>
@endsection
