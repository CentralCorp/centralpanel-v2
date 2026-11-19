@extends('layouts.admin')

@section('title', __('messages.ui.title'))
@section('page-title', __('messages.ui.header'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.ui.update') }}" method="POST">
            @csrf

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-megaphone"></i>{{ __('messages.ui.alert_section') }}</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input type="hidden" name="alert_activation" value="0">
                            <input type="checkbox" id="alert_activation" name="alert_activation" class="form-check-input" value="1" {{ old('alert_activation', $uiOptions->alert_activation) ? 'checked' : '' }}>
                            <label for="alert_activation" class="form-check-label">{{ __('messages.ui.alert_enable') }}</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input type="hidden" name="alert_scroll" value="0">
                            <input type="checkbox" id="alert_scroll" name="alert_scroll" class="form-check-input" value="1" {{ old('alert_scroll', $uiOptions->alert_scroll) ? 'checked' : '' }}>
                            <label for="alert_scroll" class="form-check-label">{{ __('messages.ui.alert_scroll') }}</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="alert_msg" class="form-label">{{ __('messages.ui.alert_content') }}</label>
                        <input type="text" class="form-control" id="alert_msg" name="alert_msg" value="{{ old('alert_msg', $uiOptions->alert_msg) }}">
                    </div>
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-youtube"></i>{{ __('messages.ui.video_section') }}</h2>
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="video_activation" value="0">
                    <input type="checkbox" id="video_activation" name="video_activation" class="form-check-input" value="1" {{ old('video_activation', $uiOptions->video_activation) ? 'checked' : '' }}>
                    <label for="video_activation" class="form-check-label">{{ __('messages.ui.video_enable') }}</label>
                </div>
                <label for="video_url" class="form-label">{{ __('messages.ui.video_url') }}</label>
                <input type="text" class="form-control" id="video_url" name="video_url" value="{{ old('video_url', $uiOptions->video_url) }}">
                <div class="form-text">{{ __('messages.ui.video_url_help') }}</div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-chat-quote"></i>{{ __('messages.ui.splash_section') }}</h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="splash" class="form-label">{{ __('messages.ui.splash_msg') }}</label>
                        <input type="text" class="form-control" id="splash" name="splash" value="{{ old('splash', $uiOptions->splash) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="splash_author" class="form-label">{{ __('messages.ui.splash_author') }}</label>
                        <input type="text" class="form-control" id="splash_author" name="splash_author" value="{{ old('splash_author', $uiOptions->splash_author) }}">
                    </div>
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-palette"></i>{{ __('messages.ui.color') }}</h2>
                <label for="accent_color" class="form-label">{{ __('messages.ui.accent_color') }}</label>
                <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" value="{{ old('accent_color', $uiOptions->accent_color ?? '#FFA500') }}" required>
            </div>

            <button type="submit" class="btn btn-primary btn-icon">
                <i class="bi bi-save"></i>
                {{ __('messages.common.update') }}
            </button>
        </form>
    </div>
</div>
@endsection
