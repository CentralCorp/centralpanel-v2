@extends('layouts.admin')

@section('title', __('messages.general.title'))
@section('page-title', __('messages.general.title'))

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('admin.general.update') }}" method="POST">
            @csrf

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-toggles"></i>{{ __('messages.general.features') }}</h2>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    @php
                        $showAzuriomOptions = in_array($options->auth_mode ?: 'azuriom', ['azuriom', 'both'], true);
                        $switches = array_values(array_filter([
                            ['id' => 'mods_enabled', 'label' => __('messages.general.mods_enabled')],
                            ['id' => 'file_verification', 'label' => __('messages.general.file_verification')],
                            ['id' => 'embedded_java', 'label' => __('messages.general.embedded_java')],
                            $showAzuriomOptions ? ['id' => 'email_verified', 'label' => __('messages.general.email_verified')] : null,
                            ['id' => 'role_display', 'label' => __('messages.general.role_display')],
                            ['id' => 'money_display', 'label' => __('messages.general.money_display')],
                        ]));
                    @endphp

                    @foreach ($switches as $switch)
                        <div class="col">
                            <div class="form-check form-switch">
                                <input type="hidden" name="{{ $switch['id'] }}" value="0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="{{ $switch['id'] }}"
                                       name="{{ $switch['id'] }}"
                                       value="1"
                                       {{ old($switch['id'], $options->{$switch['id']}) ? 'checked' : '' }}>
                                <label class="form-check-label" for="{{ $switch['id'] }}">{{ $switch['label'] }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel-muted-surface p-3 mb-4">
                <h2 class="panel-section-title"><i class="bi bi-cpu"></i>{{ __('messages.general.technical_settings') }}</h2>

                <div class="mb-3">
                    <label for="game_folder_name" class="form-label">{{ __('messages.general.game_folder_name') }}</label>
                    <input type="text"
                           class="form-control"
                           id="game_folder_name"
                           name="game_folder_name"
                           placeholder="centralcorp"
                           value="{{ old('game_folder_name', $options->game_folder_name) }}">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="default_min_ram" class="form-label">{{ __('messages.general.min_ram') }}</label>
                        <input type="number"
                               class="form-control"
                               id="default_min_ram"
                               name="min_ram"
                               min="512"
                               max="65536"
                               value="{{ old('min_ram', $options->min_ram ?? 2048) }}">
                    </div>

                    <div class="col-md-6">
                        <label for="default_max_ram" class="form-label">{{ __('messages.general.max_ram') }}</label>
                        <input type="number"
                               class="form-control"
                               id="default_max_ram"
                               name="max_ram"
                               min="512"
                               max="65536"
                               value="{{ old('max_ram', $options->max_ram ?? 4096) }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-icon">
                <i class="bi bi-save"></i>
                {{ __('messages.common.update') }}
            </button>
        </form>
    </div>
</div>
@endsection
