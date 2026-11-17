@extends('layouts.admin')

@section('title', __('messages.mods.title'))
@section('page-title', __('messages.mods.header'))

@section('content')
@if(isset($activeInstance))
    <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
        <span>
            <i class="bi bi-layers me-2"></i>
            {{ __('messages.mods.active_instance_hint', ['name' => $activeInstance->name, 'dir' => $activeInstance->resolvedDataDir()]) }}
        </span>
        <a href="{{ route('admin.file-manager') }}" class="btn btn-sm btn-outline-primary btn-icon">
            <i class="bi bi-folder"></i>
            {{ __('messages.sidebar.file_manager') }}
        </a>
    </div>
@endif

<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.mods.mod_details') }}</h2>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label for="optionalMods" class="form-label">{{ __('messages.mods.select_mod') }}</label>
                    <select id="optionalMods" name="selectedMod" class="form-select">
                        <option value="">{{ __('messages.mods.select_placeholder') }}</option>
                        @foreach ($optionalMods as $mod)
                            <option value="{{ $mod->id }}" {{ old('selectedMod', $selectedModId) == $mod->id ? 'selected' : '' }}>
                                {{ $mod->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="modDetails" class="{{ $selectedModId ? '' : 'd-none' }}">
                    <form method="POST" action="{{ route('admin.mods.updateOptional') }}" enctype="multipart/form-data" id="modForm">
                        @csrf
                        <input type="hidden" name="mod_id" id="mod_id" value="{{ $selectedModId ?? '' }}">

                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.mods.mod_file') }}</label>
                            <input type="text" id="mod_file" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.mods.mod_name') }}</label>
                            <input type="text" name="optional_name" id="optional_name" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.mods.description') }}</label>
                            <textarea name="optional_description" id="optional_description" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.mods.current_image') }}</label>
                            <div>
                                <img id="current_image" src="" alt="" class="rounded d-none mb-2" width="64" height="64" style="object-fit: cover;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('messages.mods.new_image') }}</label>
                            <input type="file" name="optional_image" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control">
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input type="checkbox" name="optional_recommended" value="1" id="optional_recommended" class="form-check-input">
                            <label class="form-check-label" for="optional_recommended">{{ __('messages.mods.recommended') }}</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-icon">
                                <i class="bi bi-save"></i>
                                {{ __('messages.mods.modify') }}
                            </button>
                            <button type="button" id="deleteBtn" class="btn btn-outline-danger btn-icon">
                                <i class="bi bi-trash"></i>
                                {{ __('messages.common.delete') }}
                            </button>
                        </div>
                    </form>
                </div>

                <div id="modEmptyState" class="empty-state {{ $selectedModId ? 'd-none' : '' }}">
                    <i class="bi bi-box-seam"></i>
                    <span>{{ __('messages.mods.select_placeholder') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ __('messages.mods.available_mods') }}</h2>
            </div>
            <div class="card-body">
                @php $optionalFiles = $optionalMods->pluck('file')->toArray(); @endphp
                <div class="list-group list-group-flush">
                    @forelse ($modsData as $mod)
                        @if (!in_array($mod['file'], $optionalFiles))
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-3 px-0">
                                <span class="text-break">{{ $mod['name'] }}</span>
                                <form method="POST" action="{{ route('admin.mods.addOptional') }}">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ $mod['file'] }}">
                                    <input type="hidden" name="name" value="{{ $mod['name'] }}">
                                    <input type="hidden" name="description" value="{{ $mod['description'] }}">
                                    <button type="submit" class="btn btn-outline-primary btn-sm btn-icon">
                                        <i class="bi bi-plus-circle"></i>
                                        {{ __('messages.mods.add_optional') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-folder-x"></i>
                            <span>{{ __('messages.common.none') }}</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const optionalModsSelect = document.getElementById('optionalMods');
        const modDetails = document.getElementById('modDetails');
        const modEmptyState = document.getElementById('modEmptyState');
        const modIdInput = document.getElementById('mod_id');
        const fileInput = document.getElementById('mod_file');
        const nameInput = document.getElementById('optional_name');
        const descriptionInput = document.getElementById('optional_description');
        const currentImage = document.getElementById('current_image');
        const recommendedCheckbox = document.getElementById('optional_recommended');
        const deleteBtn = document.getElementById('deleteBtn');
        const modForm = document.getElementById('modForm');

        function resetDetails() {
            modIdInput.value = '';
            fileInput.value = '';
            nameInput.value = '';
            descriptionInput.value = '';
            currentImage.src = '';
            currentImage.classList.add('d-none');
            recommendedCheckbox.checked = false;
            modDetails.classList.add('d-none');
            modEmptyState.classList.remove('d-none');
        }

        function loadDetails(selectedModId) {
            if (!selectedModId) {
                resetDetails();
                return;
            }

            fetch(`/admin/mods/${selectedModId}`)
                .then(response => response.json())
                .then(data => {
                    modIdInput.value = data.id;
                    fileInput.value = data.file || '';
                    nameInput.value = data.name || '';
                    descriptionInput.value = data.description || '';
                    recommendedCheckbox.checked = Boolean(data.recommended);

                    if (data.icon) {
                        currentImage.src = `/storage/${data.icon}`;
                        currentImage.classList.remove('d-none');
                    } else {
                        currentImage.classList.add('d-none');
                    }

                    modDetails.classList.remove('d-none');
                    modEmptyState.classList.add('d-none');
                })
                .catch(error => console.error('{{ __('messages.common.error') }}:', error));
        }

        optionalModsSelect.addEventListener('change', () => loadDetails(optionalModsSelect.value));

        modForm.addEventListener('submit', (event) => {
            if (!optionalModsSelect.value && window.Swal) {
                event.preventDefault();
                Swal.fire('{{ __('messages.common.error') }}', '{{ __('messages.mods.error_select_edit') }}', 'error');
            }
        });

        deleteBtn.addEventListener('click', () => {
            const selectedModId = optionalModsSelect.value;
            if (!selectedModId) {
                if (window.Swal) Swal.fire('{{ __('messages.common.error') }}', '{{ __('messages.mods.error_select_delete') }}', 'error');
                return;
            }

            const submitDelete = () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/admin/mods/delete/${selectedModId}`;
                form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
                document.body.appendChild(form);
                form.submit();
            };

            if (window.Swal) {
                Swal.fire({
                    title: '{{ __('messages.mods.confirm_delete') }}',
                    text: '{{ __('messages.mods.delete_warning') }}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '{{ __('messages.mods.yes_delete') }}',
                    cancelButtonText: '{{ __('messages.common.cancel') }}'
                }).then((result) => {
                    if (result.isConfirmed) submitDelete();
                });
            } else if (window.confirm('{{ __('messages.mods.confirm_delete') }}')) {
                submitDelete();
            }
        });

        loadDetails(optionalModsSelect.value);
    });
</script>
@endpush
