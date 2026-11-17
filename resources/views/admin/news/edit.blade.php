@extends('layouts.admin')

@section('title', __('messages.news.edit_title', ['title' => $article->title]))

@section('content')
<x-admin.page-header :title="__('messages.news.edit_title', ['title' => $article->title])" icon="bi-pencil">
    <a href="{{ route('admin.news') }}" class="btn btn-outline-secondary btn-icon">
        <i class="bi bi-arrow-left"></i>
        {{ __('messages.common.back') }}
    </a>
</x-admin.page-header>

<form action="{{ route('admin.news.update', $article) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('admin.news._form', ['article' => $article])
</form>
@endsection
