@extends('layouts.admin')

@section('title', __('messages.news.title'))

@section('content')
<x-admin.page-header :title="__('messages.news.header')" :subtitle="__('messages.news.subtitle')" icon="bi-newspaper">
    <a href="{{ route('admin.news.create') }}" class="btn btn-primary btn-icon">
        <i class="bi bi-plus-circle"></i>
        {{ __('messages.news.create_btn') }}
    </a>
</x-admin.page-header>

<div class="card shadow-sm">
    <div class="card-body">
        @if($articles->isEmpty())
            <div class="empty-state">
                <i class="bi bi-newspaper"></i>
                <span>{{ __('messages.news.no_news') }}</span>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('messages.news.article_title') }}</th>
                            <th>{{ __('messages.news.author') }}</th>
                            <th>{{ __('messages.news.badge') }}</th>
                            <th>{{ __('messages.common.status') }}</th>
                            <th>{{ __('messages.news.published_at') }}</th>
                            <th class="text-end">{{ __('messages.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($articles as $article)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @php $img = $article->resolvedImageUrl(); @endphp
                                        @if($img)
                                            <img src="{{ $img }}" alt="" width="56" height="36" class="rounded" style="object-fit: cover;">
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $article->title }}</div>
                                            <div class="text-muted small font-monospace">{{ $article->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $article->author }}</td>
                                <td>
                                    @if($article->badge)
                                        <span class="badge text-bg-info">{{ $article->badge }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($article->is_pinned)
                                        <span class="badge text-bg-warning"><i class="bi bi-pin-angle-fill me-1"></i>{{ __('messages.news.pinned_badge') }}</span>
                                    @endif
                                    @if($article->is_published)
                                        <span class="badge text-bg-success">{{ __('messages.news.published_badge') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('messages.news.draft_badge') }}</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    {{ ($article->published_at ?? $article->created_at)?->format('Y-m-d H:i') }}
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('admin.news.edit', $article) }}" class="btn btn-sm btn-outline-secondary btn-square" title="{{ __('messages.common.edit') }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('admin.news.destroy', $article) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-square"
                                                    title="{{ __('messages.common.delete') }}"
                                                    data-confirm="{{ __('messages.news.confirm_delete') }}">
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
        @endif
    </div>
</div>
@endsection
