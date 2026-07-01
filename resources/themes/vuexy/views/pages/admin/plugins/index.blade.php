<x-layout-dashboard title="{{ __('Plugins') }}">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-custom-icon mb-0">
                <li class="breadcrumb-item">
                    <a href="javascript:void(0);">{{ __('Admin') }}</a>
                    <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
                </li>
                <li class="breadcrumb-item active">{{ __('Plugins') }}</li>
            </ol>
        </nav>
    </div>

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    @if (!empty($pendingReplace))
    <div class="card shadow-none border border-warning mb-4">
        <div class="card-header bg-label-warning d-flex align-items-center gap-2 pb-3">
            <i class="ti tabler-alert-triangle fs-4 text-warning"></i>
            <h5 class="card-title text-warning mb-0">{{ __('Plugin Already Installed - Same Version Detected') }}</h5>
        </div>
        <div class="card-body pt-3">
            <p class="mb-4">{{ __('The uploaded plugin ":name" (v:version) is already installed with the same version. Would you like to replace it?', ['name' => $pendingReplace['incoming']['name'], 'version' => $pendingReplace['incoming']['version']]) }}</p>
            <div class="row gy-3 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-none border border-secondary h-100">
                        <div class="card-header bg-label-secondary py-2">
                            <strong class="text-heading">{{ __('Installed Version') }}</strong>
                        </div>
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Name') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['existing']['name'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Version') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['existing']['version'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Author') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['existing']['author'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ __('Description') }}</span>
                                <span class="fw-medium text-end" style="max-width: 60%;">{{ $pendingReplace['existing']['description'] ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-none border border-primary h-100">
                        <div class="card-header bg-label-primary py-2">
                            <strong class="text-primary">{{ __('Incoming Version') }}</strong>
                        </div>
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Name') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['incoming']['name'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Version') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['incoming']['version'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">{{ __('Author') }}</span>
                                <span class="fw-medium">{{ $pendingReplace['incoming']['author'] ?? '-' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ __('Description') }}</span>
                                <span class="fw-medium text-end" style="max-width: 60%;">{{ $pendingReplace['incoming']['description'] ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('admin.plugins.replace-confirm') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="replace">
                    <button type="submit" class="btn btn-warning">
                        <i class="ti tabler-refresh me-2"></i>{{ __('Replace Plugin') }}
                    </button>
                </form>
                <form action="{{ route('admin.plugins.replace-confirm') }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-label-secondary">
                        <i class="ti tabler-x me-2"></i>{{ __('Cancel') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0 d-flex align-items-center">
                <i class="ti tabler-upload me-2 text-primary"></i>{{ __('Upload Plugin') }}
            </h5>
            <a href="{{ route('admin.plugins.marketplace') }}" class="btn btn-sm btn-outline-primary">
                <i class="ti tabler-world me-2"></i>{{ __('Marketplace') }}
            </a>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.plugins.upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <label for="plugin_zip" class="form-label">{{ __('Select Plugin Archive (.zip)') }}</label>
                <div class="d-flex flex-column flex-md-row gap-3 align-items-start">
                    <div class="flex-grow-1 w-100">
                        <input type="file" class="form-control form-control-sm" id="plugin_zip" name="plugin_zip" accept=".zip" required>
                        <div class="form-text mt-2">{{ __('Ensure the zip file contains a valid info.json in its root directory.') }}</div>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <i class="ti tabler-cloud-upload me-2"></i> {{ __('Install') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 d-flex align-items-center">
            <i class="ti tabler-puzzle me-2 text-primary"></i>{{ __('Installed Plugins') }}
        </h5>
        <span class="badge bg-label-primary px-3 py-2">{{ count($plugins) }} {{ __('plugins') }}</span>
    </div>

    @if (empty($plugins))
        <div class="card shadow-none border border-dashed text-center py-5">
            <div class="card-body py-5">
                <div class="avatar avatar-xl bg-label-secondary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                    <i class="ti tabler-puzzle-off fs-2"></i>
                </div>
                <h5 class="text-heading mb-1">{{ __('No plugins installed') }}</h5>
                <p class="text-muted mb-0">{{ __('Upload a .zip plugin file above to get started.') }}</p>
            </div>
        </div>
    @else
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ti tabler-search"></i></span>
                            <input type="text" class="form-control" id="installedPluginSearch" placeholder="{{ __('Search installed plugins...') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="installedPluginSort">
                            <option value="updates_first">{{ __('Updates First') }}</option>
                            <option value="date_desc">{{ __('Date Added (Newest)') }}</option>
                            <option value="name_asc">{{ __('Name (A-Z)') }}</option>
                            <option value="name_desc">{{ __('Name (Z-A)') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="text-muted small" id="installedResultCount"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row gy-4" id="installedPluginsContainer">
            @foreach ($plugins as $plugin)
            <div class="col-sm-6 col-lg-4 installed-plugin-card" data-name="{{ strtolower($plugin['name'] ?? $plugin['slug']) }}" data-slug="{{ strtolower($plugin['slug']) }}" data-description="{{ strtolower($plugin['description'] ?? '') }}" data-installed="{{ $plugin['installed_at'] ?? '2025-01-01' }}" data-has-update="{{ !empty($pluginUpdates[$plugin['slug']]) ? '1' : '0' }}">
                <div class="card h-100 border-0 shadow-sm overflow-hidden">
                    <div class="position-relative" style="height: 180px;">
                        @if (!empty($plugin['screenshot']))
                            <img src="{{ $plugin['screenshot'] }}" class="card-img-top w-100" style="object-fit: cover;">
                        @else
                            <div class="card-img-top w-100 d-flex align-items-center justify-content-center bg-label-secondary" style="height: 180px;">
                                <i class="ti tabler-puzzle fs-1 text-secondary opacity-50"></i>
                            </div>
                        @endif
                        <div class="position-absolute top-0 end-0 p-3 d-flex flex-column gap-1 align-items-end">
                            <div class="d-flex gap-1">
                                @if (!empty($plugin['official']) && $plugin['official'] == '1')
                                    <span class="badge bg-info shadow-sm d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Official') }}"><i class="ti tabler-rosette-discount-check icon-xs"></i></span>
                                @endif
                                @if (!empty($plugin['pro']) && $plugin['pro'] == '1')
                                    @if (!empty($plugin['pro_url']))
                                        <a href="{{ $plugin['pro_url'] }}" target="_blank" rel="noopener" class="badge bg-danger shadow-sm d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="{{ __('Paid - License required') }}"><i class="ti tabler-currency-dollar icon-xs"></i></a>
                                    @else
                                        <span class="badge bg-danger shadow-sm d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Paid - License required') }}"><i class="ti tabler-currency-dollar icon-xs"></i></span>
                                    @endif
                                @endif
                                @if ($plugin['is_enabled'])
                                    <span class="badge bg-success shadow-sm d-inline-flex align-items-center"><i class="ti tabler-check icon-xs me-1"></i>{{ __('Enabled') }}</span>
                                @else
                                    <span class="badge bg-secondary shadow-sm d-inline-flex align-items-center"><i class="ti tabler-x icon-xs me-1"></i>{{ __('Disabled') }}</span>
                                @endif
                            </div>
                            @if (!empty($pluginUpdates[$plugin['slug']]))
                                <span class="badge bg-warning shadow-sm d-inline-flex align-items-center"><i class="ti tabler-refresh icon-xs me-1"></i>v{{ $pluginUpdates[$plugin['slug']]['new_version'] }}</span>
                            @endif
                        </div>
						@if (!empty($plugin['donation']))
							<a href="{{ $plugin['donation'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-warning position-absolute bottom-0 start-0 mb-4 ms-3" data-bs-toggle="tooltip" title="{{ __('Support Developer') }}">
								<i class="ti tabler-heart me-1"></i> {{ __('Donate') }}
							</a>
						@endif
                    </div>

                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <h5 class="card-title mb-1 text-truncate flex-grow-1">{{ $plugin['name'] ?? $plugin['slug'] }}</h5>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    @if (!empty($plugin['has_readme']))
                                        <button type="button" class="btn btn-sm btn-icon btn-label-info plugin-file-btn" data-slug="{{ $plugin['slug'] }}" data-file="README.md" data-bs-toggle="tooltip" title="{{ __('README') }}">
                                            <i class="ti tabler-file-text"></i>
                                        </button>
                                    @endif
                                    @if (!empty($plugin['has_changelog']))
                                        <button type="button" class="btn btn-sm btn-icon btn-label-secondary plugin-file-btn" data-slug="{{ $plugin['slug'] }}" data-file="CHANGELOG.md" data-bs-toggle="tooltip" title="{{ __('Changelog') }}">
                                            <i class="ti tabler-list-details"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="text-muted small d-flex align-items-center">
                                <i class="ti tabler-user-circle icon-xs me-1"></i>
                                @if (!empty($plugin['website']))
                                    <a href="{{ $plugin['website'] }}" target="_blank" rel="noopener" class="text-muted text-decoration-none">{{ $plugin['author'] ?? __('Unknown Author') }}</a>
                                @else
                                    {{ $plugin['author'] ?? __('Unknown Author') }}
                                @endif
                            </div>
                        </div>

                        <p class="card-text text-muted small flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            {{ $plugin['description'] ?? __('No description available.') }}
                        </p>

                        <div class="d-flex flex-wrap gap-2 mt-3 mb-1">
                            <span class="badge bg-label-secondary d-inline-flex align-items-center"><i class="ti tabler-tag icon-xs me-1"></i> v{{ $plugin['version'] ?? '?' }}</span>
                            <span class="badge bg-label-info d-inline-flex align-items-center"><i class="ti tabler-versions icon-xs me-1"></i> {{ $plugin['compatibility'] ?? '-' }}</span>
                            @if (!empty($plugin['license']))
                                @if (!empty($plugin['license_url']))
                                    <a href="{{ $plugin['license_url'] }}" target="_blank" rel="noopener" class="badge bg-label-secondary d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="{{ $plugin['license'] }}"><i class="ti tabler-copyright icon-xs text-danger"></i></a>
                                @else
                                    <span class="badge bg-label-secondary d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ $plugin['license'] }}"><i class="ti tabler-copyright icon-xs text-danger"></i></span>
                                @endif
                            @endif
                            @if (!empty($plugin['missing_dependencies']))
                                <span class="badge bg-label-danger d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Requires') }}: {{ implode(', ', $plugin['missing_dependencies']) }}"><i class="ti tabler-alert-triangle icon-xs me-1"></i> {{ __('Missing Dependencies') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top d-flex justify-content-between align-items-center py-3">
                        <div class="text-muted small">
                            {{ $plugin['installed_at'] ? \Carbon\Carbon::parse($plugin['installed_at'])->format('M d, Y') : '-' }}
                        </div>
                        <div class="d-flex gap-2">
                            @if (!empty($pluginUpdates[$plugin['slug']]) && !empty($pluginUpdates[$plugin['slug']]['download_url']))
                                <form action="{{ route('admin.plugins.marketplace.install') }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="download_url" value="{{ $pluginUpdates[$plugin['slug']]['download_url'] }}">
                                    <input type="hidden" name="slug" value="{{ $plugin['slug'] }}">
                                    <button type="submit" class="btn btn-sm btn-icon btn-warning" data-bs-toggle="tooltip" title="{{ __('Update to v:version', ['version' => $pluginUpdates[$plugin['slug']]['new_version']]) }}">
                                        <i class="ti tabler-refresh"></i>
                                    </button>
                                </form>
                            @endif
                            @if ($plugin['in_database'])
                                @if ($plugin['is_enabled'])
                                    <form action="{{ route('admin.plugins.disable', $plugin['slug']) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-icon btn-label-warning" data-bs-toggle="tooltip" title="{{ __('Disable') }}">
                                            <i class="ti tabler-player-pause"></i>
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.plugins.enable', $plugin['slug']) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-icon btn-label-success" @if(!empty($plugin['missing_dependencies'])) disabled @endif data-bs-toggle="tooltip" title="{{ !empty($plugin['missing_dependencies']) ? __('Requires') . ': ' . implode(', ', $plugin['missing_dependencies']) : __('Enable') }}">
                                            <i class="ti tabler-player-play"></i>
                                        </button>
                                    </form>
                                @endif
                                <button type="button" class="btn btn-sm btn-icon btn-label-danger"
                                    onclick="confirmUninstall('{{ $plugin['slug'] }}', '{{ addslashes($plugin['name'] ?? $plugin['slug']) }}')"
                                    data-bs-toggle="tooltip" title="{{ __('Uninstall') }}">
                                    <i class="ti tabler-trash"></i>
                                </button>
                            @else
                                <form action="{{ route('admin.plugins.enable', $plugin['slug']) }}" method="POST" class="d-inline w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-label-primary w-100" @if(!empty($plugin['missing_dependencies'])) disabled @endif>
                                        <i class="ti tabler-plus me-1"></i>{{ __('Register Plugin') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="d-none text-center py-5" id="installedNoResults">
            <div class="avatar avatar-xl bg-label-secondary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                <i class="ti tabler-search-off fs-2"></i>
            </div>
            <h5 class="text-heading mb-1">{{ __('No plugins found') }}</h5>
            <p class="text-muted mb-0">{{ __('Try a different search term.') }}</p>
        </div>

        <nav id="installedPluginPagination" class="mt-4">
            <ul class="pagination justify-content-center mb-0"></ul>
        </nav>
    @endif

    <div class="modal fade" id="uninstallModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="uninstallForm" method="POST" class="modal-content">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Uninstall Plugin') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="ti tabler-trash text-danger icon-48px mb-2"></i>
                        <h5 class="mb-0">{{ __('Are you sure?') }}</h5>
                        <p class="text-muted">{{ __('You are about to uninstall') }} <strong id="uninstallPluginName" class="text-heading"></strong>.</p>
                    </div>
                    <div class="alert alert-danger d-flex align-items-start mb-0" role="alert">
                        <span class="alert-icon text-danger me-2" style="background-color: unset;">
                            <i class="ti tabler-alert-circle fs-5"></i>
                        </span>
                        <div>
                            {{ __('This will rollback migrations, delete plugin files, and remove it from the database. This action cannot be undone.') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer pb-3 px-4 border-0 justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary m-0" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-outline-danger m-0">
                        <i class="ti tabler-trash me-2"></i>{{ __('Uninstall Permanently') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="pluginFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title d-flex align-items-center" id="pluginFileModalTitle">
                        <i class="ti tabler-file-text me-2 text-primary"></i>
                        <span id="pluginFileModalName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="pluginFileModalBody">
                    <div class="text-center py-5" id="pluginFileLoading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Loading...') }}</span>
                        </div>
                    </div>
                    <div id="pluginFileContent" class="markdown-body" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .markdown-body h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: .75rem; border-bottom: 1px solid var(--bs-border-color); padding-bottom: .5rem; }
        .markdown-body h2 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: .5rem; }
        .markdown-body h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.25rem; margin-bottom: .5rem; }
        .markdown-body p { margin-bottom: .75rem; }
        .markdown-body ul, .markdown-body ol { padding-left: 1.5rem; margin-bottom: .75rem; }
        .markdown-body li { margin-bottom: .25rem; }
        .markdown-body code { background: var(--bs-gray-200); padding: .125rem .375rem; border-radius: .25rem; font-size: .875em; }
        .markdown-body pre { background: var(--bs-gray-200); padding: 1rem; border-radius: .5rem; overflow-x: auto; margin-bottom: 1rem; }
        .markdown-body pre code { background: none; padding: 0; }
        .markdown-body blockquote { border-left: 3px solid var(--bs-primary); padding-left: 1rem; color: var(--bs-secondary); margin-bottom: .75rem; }
        .markdown-body table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        .markdown-body th, .markdown-body td { border: 1px solid var(--bs-border-color); padding: .5rem; text-align: left; }
        .markdown-body th { background: var(--bs-gray-100); font-weight: 600; }
        .markdown-body a { color: var(--bs-primary); }
        .markdown-body img { max-width: 100%; border-radius: .5rem; }
        .markdown-body hr { border-top: 1px solid var(--bs-border-color); margin: 1.5rem 0; }
    </style>

    <script>
        function confirmUninstall(slug, name) {
            document.getElementById('uninstallPluginName').textContent = name;
            document.getElementById('uninstallForm').action = '{{ url("admin/plugins") }}/' + slug;
            var modal = new bootstrap.Modal(document.getElementById('uninstallModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) {
                return new bootstrap.Tooltip(el);
            });

            var perPage = 12;
            var currentPage = 1;
            var container = document.getElementById('installedPluginsContainer');
            if (!container) return;

            var allCards = Array.from(container.querySelectorAll('.installed-plugin-card'));
            var filteredCards = allCards.slice();
            var searchInput = document.getElementById('installedPluginSearch');
            var sortSelect = document.getElementById('installedPluginSort');
            var pagination = document.querySelector('#installedPluginPagination .pagination');
            var noResults = document.getElementById('installedNoResults');
            var resultCount = document.getElementById('installedResultCount');

            function sortCards(cards, mode) {
                var sorted = cards.slice();
                if (mode === 'name_asc') {
                    sorted.sort(function (a, b) {
                        return a.dataset.name.localeCompare(b.dataset.name);
                    });
                } else if (mode === 'name_desc') {
                    sorted.sort(function (a, b) {
                        return b.dataset.name.localeCompare(a.dataset.name);
                    });
                } else if (mode === 'updates_first') {
                    sorted.sort(function (a, b) {
                        var aUpd = a.dataset.hasUpdate === '1' ? 0 : 1;
                        var bUpd = b.dataset.hasUpdate === '1' ? 0 : 1;
                        if (aUpd !== bUpd) return aUpd - bUpd;
                        return (b.dataset.installed || '').localeCompare(a.dataset.installed || '');
                    });
                } else {
                    sorted.sort(function (a, b) {
                        return (b.dataset.installed || '').localeCompare(a.dataset.installed || '');
                    });
                }
                return sorted;
            }

            function renderPage() {
                var totalPages = Math.ceil(filteredCards.length / perPage);
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                allCards.forEach(function (card) {
                    card.style.display = 'none';
                });

                var start = (currentPage - 1) * perPage;
                var end = start + perPage;
                var pageCards = filteredCards.slice(start, end);

                pageCards.forEach(function (card) {
                    card.style.display = '';
                });

                if (filteredCards.length === 0) {
                    noResults.classList.remove('d-none');
                    resultCount.textContent = '';
                } else {
                    noResults.classList.add('d-none');
                    resultCount.textContent = filteredCards.length + ' {{ __("plugins") }}';
                }

                renderPagination(totalPages);
            }

            function renderPagination(totalPages) {
                pagination.innerHTML = '';
                if (totalPages <= 1) return;

                var prevLi = document.createElement('li');
                prevLi.className = 'page-item' + (currentPage === 1 ? ' disabled' : '');
                prevLi.innerHTML = '<a class="page-link" href="javascript:void(0);"><i class="ti tabler-chevron-left icon-xs"></i></a>';
                prevLi.addEventListener('click', function () {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPage();
                    }
                });
                pagination.appendChild(prevLi);

                for (var i = 1; i <= totalPages; i++) {
                    (function (page) {
                        var li = document.createElement('li');
                        li.className = 'page-item' + (page === currentPage ? ' active' : '');
                        li.innerHTML = '<a class="page-link" href="javascript:void(0);">' + page + '</a>';
                        li.addEventListener('click', function () {
                            currentPage = page;
                            renderPage();
                        });
                        pagination.appendChild(li);
                    })(i);
                }

                var nextLi = document.createElement('li');
                nextLi.className = 'page-item' + (currentPage === totalPages ? ' disabled' : '');
                nextLi.innerHTML = '<a class="page-link" href="javascript:void(0);"><i class="ti tabler-chevron-right icon-xs"></i></a>';
                nextLi.addEventListener('click', function () {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderPage();
                    }
                });
                pagination.appendChild(nextLi);
            }

            function applyFilters() {
                var query = searchInput.value.trim().toLowerCase();
                var sortMode = sortSelect.value;

                if (query.length >= 3) {
                    filteredCards = allCards.filter(function (card) {
                        return card.dataset.name.indexOf(query) !== -1 ||
                               card.dataset.slug.indexOf(query) !== -1 ||
                               card.dataset.description.indexOf(query) !== -1;
                    });
                } else {
                    filteredCards = allCards.slice();
                }

                filteredCards = sortCards(filteredCards, sortMode);

                filteredCards.forEach(function (card) {
                    container.appendChild(card);
                });

                currentPage = 1;
                renderPage();
            }

            searchInput.addEventListener('input', applyFilters);
            sortSelect.addEventListener('change', applyFilters);

            applyFilters();

            var fileModalEl = document.getElementById('pluginFileModal');
            var fileModal = new bootstrap.Modal(fileModalEl);
            var fileButtons = document.querySelectorAll('.plugin-file-btn');

            fileButtons.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var slug = this.dataset.slug;
                    var filename = this.dataset.file;
					var cleanName = filename.replace(/\.md$/i, '');
					var formatted = cleanName.charAt(0).toUpperCase() + cleanName.slice(1).toLowerCase();
					document.getElementById('pluginFileModalName').textContent = slug + ' - ' + formatted;
                    document.getElementById('pluginFileLoading').style.display = '';
                    document.getElementById('pluginFileContent').style.display = 'none';
                    document.getElementById('pluginFileContent').innerHTML = '';
                    fileModal.show();

                    fetch('{{ url("admin/plugins") }}/' + slug + '/file/' + filename)
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            document.getElementById('pluginFileLoading').style.display = 'none';
                            var contentEl = document.getElementById('pluginFileContent');
                            if (data.html) {
                                contentEl.innerHTML = data.html;
                            } else {
                                contentEl.innerHTML = '<p class="text-muted text-center py-4">' + (data.error || '{{ __("File not found.") }}') + '</p>';
                            }
                            contentEl.style.display = '';
                        })
                        .catch(function () {
                            document.getElementById('pluginFileLoading').style.display = 'none';
                            var contentEl = document.getElementById('pluginFileContent');
                            contentEl.innerHTML = '<p class="text-muted text-center py-4">{{ __("Failed to load file.") }}</p>';
                            contentEl.style.display = '';
                        });
                });
            });
        });
    </script>
</x-layout-dashboard>
