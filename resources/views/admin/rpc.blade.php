@extends('layouts.admin')

@section('title', __('messages.rpc.title'))
@section('page-title', __('messages.rpc.title'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.rpc.update') }}" method="POST">
            @csrf

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-power"></i>{{ __('messages.rpc.activation') }}</h2>
                <div class="form-check form-switch">
                    <input type="hidden" name="rpc_activation" value="0">
                    <input type="checkbox" id="rpc_activation" name="rpc_activation" class="form-check-input" value="1"
                           {{ old('rpc_activation', optional($rpcOptions)->rpc_activation) ? 'checked' : '' }}>
                    <label for="rpc_activation" class="form-check-label">{{ __('messages.rpc.enable_rpc') }}</label>
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-info-circle"></i>{{ __('messages.rpc.general_info') }}</h2>

                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="bi bi-code-slash me-1"></i>{{ __('messages.rpc.placeholders_help') }}
                </div>

                <div class="mb-3">
                    <label for="rpc_id" class="form-label">{{ __('messages.rpc.client_id') }}</label>
                    <input type="text" class="form-control" id="rpc_id" name="rpc_id"
                           value="{{ old('rpc_id', optional($rpcOptions)->rpc_id) }}">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="rpc_details" class="form-label">{{ __('messages.rpc.details') }}</label>
                        <input type="text" class="form-control" id="rpc_details" name="rpc_details"
                               placeholder="Ex: {instance} ({version})"
                               value="{{ old('rpc_details', optional($rpcOptions)->rpc_details) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_state" class="form-label">{{ __('messages.rpc.state') }}</label>
                        <input type="text" class="form-control" id="rpc_state" name="rpc_state"
                               placeholder="Ex: {player} - {server}"
                               value="{{ old('rpc_state', optional($rpcOptions)->rpc_state) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_large_image" class="form-label">{{ __('messages.rpc.large_image_key') }}</label>
                        <input type="text" class="form-control" id="rpc_large_image" name="rpc_large_image"
                               placeholder="large"
                               value="{{ old('rpc_large_image', optional($rpcOptions)->rpc_large_image ?? 'large') }}">
                        <div class="form-text">{{ __('messages.rpc.image_key_help') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_large_text" class="form-label">{{ __('messages.rpc.large_image_text') }}</label>
                        <input type="text" class="form-control" id="rpc_large_text" name="rpc_large_text"
                               value="{{ old('rpc_large_text', optional($rpcOptions)->rpc_large_text) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_small_image" class="form-label">{{ __('messages.rpc.small_image_key') }}</label>
                        <input type="text" class="form-control" id="rpc_small_image" name="rpc_small_image"
                               placeholder="small"
                               value="{{ old('rpc_small_image', optional($rpcOptions)->rpc_small_image ?? 'small') }}">
                        <div class="form-text">{{ __('messages.rpc.image_key_help') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_small_text" class="form-label">{{ __('messages.rpc.small_image_text') }}</label>
                        <input type="text" class="form-control" id="rpc_small_text" name="rpc_small_text"
                               value="{{ old('rpc_small_text', optional($rpcOptions)->rpc_small_text) }}">
                    </div>
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-link-45deg"></i>{{ __('messages.rpc.custom_buttons') }}</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="rpc_button1" class="form-label">{{ __('messages.rpc.button1_name') }}</label>
                        <input type="text" class="form-control" id="rpc_button1" name="rpc_button1"
                               value="{{ old('rpc_button1', optional($rpcOptions)->rpc_button1) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_button1_url" class="form-label">{{ __('messages.rpc.button1_url') }}</label>
                        <input type="url" class="form-control" id="rpc_button1_url" name="rpc_button1_url"
                               value="{{ old('rpc_button1_url', optional($rpcOptions)->rpc_button1_url) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_button2" class="form-label">{{ __('messages.rpc.button2_name') }}</label>
                        <input type="text" class="form-control" id="rpc_button2" name="rpc_button2"
                               value="{{ old('rpc_button2', optional($rpcOptions)->rpc_button2) }}">
                    </div>
                    <div class="col-md-6">
                        <label for="rpc_button2_url" class="form-label">{{ __('messages.rpc.button2_url') }}</label>
                        <input type="url" class="form-control" id="rpc_button2_url" name="rpc_button2_url"
                               value="{{ old('rpc_button2_url', optional($rpcOptions)->rpc_button2_url) }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-icon">
                <i class="bi bi-save"></i>
                {{ __('messages.rpc.update_btn') }}
            </button>
        </form>
    </div>
</div>
@endsection
