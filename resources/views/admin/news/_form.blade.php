@php
    $isEdit = isset($article);
    $publishedValue = old(
        'published_at',
        isset($article?->published_at) ? $article->published_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')
    );
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="title" class="form-label">{{ __('messages.news.article_title') }}</label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="{{ old('title', $article->title ?? '') }}" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="slug" class="form-label">{{ __('messages.news.slug') }}</label>
                        <input type="text" class="form-control font-monospace" id="slug" name="slug"
                               value="{{ old('slug', $article->slug ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="badge" class="form-label">{{ __('messages.news.badge') }}</label>
                        <input type="text" class="form-control" id="badge" name="badge"
                               value="{{ old('badge', $article->badge ?? '') }}"
                               placeholder="{{ __('messages.news.badge_placeholder') }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="newsContent" class="form-label">{{ __('messages.news.content') }}</label>
                    <textarea class="form-control font-monospace" id="newsContent" name="content" rows="10" required>{{ old('content', $article->content ?? '') }}</textarea>
                </div>

                <div>
                    <label class="form-label">{{ __('messages.news.preview') }}</label>
                    <div class="panel-muted-surface p-3 rounded" id="newsPreview" style="min-height: 100px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="author" class="form-label">{{ __('messages.news.author') }}</label>
                    <input type="text" class="form-control" id="author" name="author"
                           value="{{ old('author', $article->author ?? Auth::user()->name) }}">
                </div>

                <div class="mb-3">
                    <label for="published_at" class="form-label">{{ __('messages.news.published_at') }}</label>
                    <input type="datetime-local" class="form-control" id="published_at" name="published_at"
                           value="{{ $publishedValue }}">
                </div>

                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" class="form-check-input" id="is_published" name="is_published" value="1"
                           {{ old('is_published', $article->is_published ?? true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_published">{{ __('messages.news.is_published') }}</label>
                </div>

                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="is_pinned" value="0">
                    <input type="checkbox" class="form-check-input" id="is_pinned" name="is_pinned" value="1"
                           {{ old('is_pinned', $article->is_pinned ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_pinned">{{ __('messages.news.is_pinned') }}</label>
                </div>

                <hr>

                <div class="mb-3">
                    <label for="image" class="form-label">{{ __('messages.news.cover_image') }}</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    @if($isEdit && $article->image_path)
                        <div class="mt-2">
                            <img src="{{ asset('storage/' . $article->image_path) }}" alt="" class="img-fluid rounded mb-2" style="max-height: 120px;">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="remove_image" value="1" id="remove_image">
                                <label class="form-check-label small" for="remove_image">{{ __('messages.news.remove_image') }}</label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label for="image_url" class="form-label">{{ __('messages.news.image_url') }}</label>
                    <input type="url" class="form-control" id="image_url" name="image_url"
                           value="{{ old('image_url', $article->image_url ?? '') }}" placeholder="https://...">
                </div>

                <div>
                    <label for="external_url" class="form-label">{{ __('messages.news.external_url') }}</label>
                    <input type="url" class="form-control" id="external_url" name="external_url"
                           value="{{ old('external_url', $article->external_url ?? '') }}" placeholder="https://...">
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-icon flex-grow-1">
                <i class="bi bi-save"></i>
                {{ __('messages.common.save') }}
            </button>
            <a href="{{ route('admin.news') }}" class="btn btn-outline-secondary">
                {{ __('messages.common.cancel') }}
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const textarea = document.getElementById('newsContent');
    const preview = document.getElementById('newsPreview');
    function updatePreview() {
        preview.innerHTML = textarea.value || '<span class="text-muted">—</span>';
    }
    textarea.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
@endpush
