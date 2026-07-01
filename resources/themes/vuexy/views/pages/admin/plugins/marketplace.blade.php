<x-layout-dashboard title="{{ __('Plugin Marketplace') }}">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-custom-icon mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.plugins.index') }}">{{ __('Plugins') }}</a>
                    <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
                </li>
                <li class="breadcrumb-item active">{{ __('Marketplace') }}</li>
            </ol>
        </nav>
    </div>

    @if (session()->has('alert'))
        <x-alert>
            @slot('type', session('alert')['type'])
            @slot('msg', session('alert')['msg'])
        </x-alert>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 d-flex align-items-center">
            <i class="ti tabler-world me-2 text-primary"></i>{{ __('Discover Plugins') }}
        </h5>
        <a href="{{ route('admin.plugins.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="ti tabler-arrow-left me-2"></i>{{ __('Back to Plugins') }}
        </a>
    </div>

    @if (empty($marketplacePlugins))
        <div class="card shadow-none border border-dashed text-center py-5">
            <div class="card-body py-5">
                <div class="avatar avatar-xl bg-label-secondary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                    <i class="ti tabler-world-off fs-2"></i>
                </div>
                <h5 class="text-heading mb-1">{{ __('Marketplace Unavailable') }}</h5>
                <p class="text-muted mb-0">{{ __('Could not fetch marketplace data. Please check your internet connection.') }}</p>
            </div>
        </div>
    @else
        @php
            $categoryMap = [];
            foreach ($marketplacePlugins as $p) {
                $cat = !empty($p['category']) ? $p['category'] : (!empty($p['catergory']) ? $p['catergory'] : 'Others');
                if (!isset($categoryMap[$cat])) {
                    $categoryMap[$cat] = 0;
                }
                $categoryMap[$cat]++;
            }
            ksort($categoryMap);
        @endphp

        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ti tabler-search"></i></span>
                            <input type="text" class="form-control" id="pluginSearch" placeholder="{{ __('Search plugins...') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="pluginSort">
                            <option value="date_desc">{{ __('Date Added (Newest)') }}</option>
                            <option value="name_asc">{{ __('Name (A-Z)') }}</option>
                            <option value="name_desc">{{ __('Name (Z-A)') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="text-muted small" id="pluginResultCount"></span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3" id="categoryFilter">
                    <button type="button" class="btn btn-xs btn-primary category-btn active" data-category="all">
                        {{ __('All') }} <span class="badge bg-white text-primary ms-1">{{ count($marketplacePlugins) }}</span>
                    </button>
                    @foreach ($categoryMap as $catName => $catCount)
                        <button type="button" class="btn btn-xs btn-outline-secondary category-btn" data-category="{{ $catName }}">
                            {{ __($catName) }} <span class="badge bg-label-secondary ms-1">{{ $catCount }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="row gy-4" id="pluginsContainer">
            @foreach ($marketplacePlugins as $index => $plugin)
            @php
                $slug = $plugin['slug'] ?? '';
                $installed = $installedPlugins->has($slug);
                $installedVersion = $installed ? ($installedPlugins->get($slug)['version'] ?? null) : null;
                $isSameVersion = $installed && $installedVersion === ($plugin['version'] ?? null);
                $requires = $plugin['requires'] ?? [];
                $missingDeps = [];
                foreach ($requires as $dep) {
                    $depPlugin = $installedPlugins->get($dep);
                    if (!$depPlugin || !($depPlugin['is_enabled'] ?? false)) {
                        $missingDeps[] = $dep;
                    }
                }
                $pluginCategory = !empty($plugin['category']) ? $plugin['category'] : (!empty($plugin['catergory']) ? $plugin['catergory'] : 'Others');
            @endphp
            <div class="col-sm-6 col-lg-4 plugin-card"
                data-name="{{ strtolower($plugin['name'] ?? '') }}"
                data-slug="{{ strtolower($slug) }}"
                data-description="{{ strtolower($plugin['description'] ?? '') }}"
                data-added="{{ $plugin['added_at'] ?? '2025-01-01' }}"
                data-index="{{ $index }}"
                data-category="{{ $pluginCategory }}"
                data-plugin="{{ htmlspecialchars(json_encode(array_merge($plugin, ['_category' => $pluginCategory]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') }}"
                data-installed="{{ $installed ? '1' : '0' }}"
                data-same-version="{{ $isSameVersion ? '1' : '0' }}">
                <div class="card h-100 border-0 shadow-sm overflow-hidden plugin-card-inner" role="button">
                    <div class="position-relative" style="height: 180px;">
                        @if (!empty($plugin['screenshot']))
                            <img src="{{ $plugin['screenshot'] }}" class="card-img-top w-100" style="object-fit: cover;" alt="{{ $plugin['name'] ?? '' }}">
                        @else
                            <div class="card-img-top w-100 d-flex align-items-center justify-content-center bg-label-secondary" style="height: 180px;">
                                <i class="ti tabler-puzzle fs-1 text-secondary opacity-50"></i>
                            </div>
                        @endif
                        <div class="position-absolute top-0 end-0 p-3 d-flex gap-1">
                            @if (!empty($plugin['official']) && $plugin['official'] == '1')
                                <span class="badge bg-info shadow-sm d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Official') }}"><i class="ti tabler-rosette-discount-check icon-xs"></i></span>
                            @endif
                            @if (!empty($plugin['pro']) && $plugin['pro'] == '1')
                                @if (!empty($plugin['pro_url']))
                                    <a href="{{ $plugin['pro_url'] }}" target="_blank" rel="noopener" class="badge bg-danger shadow-sm d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="{{ __('Paid - License required') }}" onclick="event.stopPropagation();"><i class="ti tabler-currency-dollar icon-xs"></i></a>
                                @else
                                    <span class="badge bg-danger shadow-sm d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Paid - License required') }}"><i class="ti tabler-currency-dollar icon-xs"></i></span>
                                @endif
                            @endif
                            @if ($installed)
                                @if ($isSameVersion)
                                    <span class="badge bg-success shadow-sm d-inline-flex align-items-center"><i class="ti tabler-check icon-xs me-1"></i>{{ __('Installed') }}</span>
                                @else
                                    <span class="badge bg-warning shadow-sm d-inline-flex align-items-center"><i class="ti tabler-refresh icon-xs me-1"></i>{{ __('Update Available') }}</span>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <h5 class="card-title mb-1 text-truncate flex-grow-1">{{ $plugin['name'] ?? $slug }}</h5>
                                @if (!empty($plugin['donation']))
                                    <a href="{{ $plugin['donation'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-warning flex-shrink-0 d-inline-flex align-items-center gap-1 px-2" onclick="event.stopPropagation();" data-bs-toggle="tooltip" title="{{ __('Support Developer') }}">
                                        <i class="ti tabler-heart"></i> {{ __('Donate') }}
                                    </a>
                                @endif
                            </div>
                            <div class="text-muted small d-flex align-items-center">
                                <i class="ti tabler-user-circle icon-xs me-1"></i>
                                @if (!empty($plugin['website']))
                                    <a href="{{ $plugin['website'] }}" target="_blank" rel="noopener" class="text-muted text-decoration-none" onclick="event.stopPropagation();">{{ $plugin['author'] ?? __('Unknown Author') }}</a>
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
                            @if (!empty($plugin['compatibility']))
                                <span class="badge bg-label-info d-inline-flex align-items-center"><i class="ti tabler-versions icon-xs me-1"></i> {{ $plugin['compatibility'] }}</span>
                            @endif
                            @if (!empty($plugin['download_num']))
                                <span class="badge bg-label-primary d-inline-flex align-items-center"><i class="ti tabler-download icon-xs me-1"></i> {{ number_format((int)$plugin['download_num']) }}</span>
                            @endif
                            @if (!empty($plugin['license']))
                                @if (!empty($plugin['license_url']))
                                    <a href="{{ $plugin['license_url'] }}" target="_blank" rel="noopener" class="badge bg-label-secondary d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="{{ $plugin['license'] }}" onclick="event.stopPropagation();"><i class="ti tabler-copyright text-danger icon-xs"></i></a>
                                @else
                                    <span class="badge bg-label-secondary d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ $plugin['license'] }}"><i class="ti tabler-copyright text-danger icon-xs"></i></span>
                                @endif
                            @endif
                            @if (!empty($missingDeps))
                                <span class="badge bg-label-danger d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __('Requires') }}: {{ implode(', ', $missingDeps) }}"><i class="ti tabler-alert-triangle icon-xs me-1"></i> {{ __('Missing Dependencies') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top p-3">
                        @if (!empty($plugin['download_url']))
                            @if ($installed && $isSameVersion)
                                <button class="btn btn-sm btn-label-secondary w-100" disabled>
                                    <i class="ti tabler-check me-2"></i>{{ __('Already Installed') }}
                                </button>
                            @else
                                <form action="{{ route('admin.plugins.marketplace.install') }}" method="POST" class="w-100" onclick="event.stopPropagation();">
                                    @csrf
                                    <input type="hidden" name="download_url" value="{{ $plugin['download_url'] }}">
                                    <input type="hidden" name="slug" value="{{ $slug }}">
                                    <button type="submit" class="btn btn-sm w-100 {{ $installed ? 'btn-warning' : 'btn-primary' }}">
                                        <i class="ti tabler-{{ $installed ? 'refresh' : 'download' }} me-2"></i>
                                        {{ $installed ? __('Update to v' . $plugin['version']) : __('Install Plugin') }}
                                    </button>
                                </form>
                            @endif
                        @else
                            <button class="btn btn-sm btn-label-secondary w-100" disabled>
                                <i class="ti tabler-link-off me-2"></i>{{ __('No Download Link') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="d-none text-center py-5" id="noResults">
            <div class="avatar avatar-xl bg-label-secondary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center">
                <i class="ti tabler-search-off fs-2"></i>
            </div>
            <h5 class="text-heading mb-1">{{ __('No plugins found') }}</h5>
            <p class="text-muted mb-0">{{ __('Try a different search term.') }}</p>
        </div>

        <nav id="pluginPagination" class="mt-4">
            <ul class="pagination justify-content-center mb-0"></ul>
        </nav>
    @endif

    <div class="modal fade" id="pluginDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title d-flex align-items-center" id="pdmTitle">
                        <i class="ti tabler-puzzle me-2 text-primary"></i>
                        <span id="pdmName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-7">
                            <div class="p-4">
                                <div class="position-relative rounded overflow-hidden bg-label-secondary" id="pdmGallery" style="max-height:320px;">
                                    <img id="pdmMainImg" src="" alt="" class="w-100" style="object-fit:cover;max-height:400px;display:none;">
                                    <div id="pdmNoImg" class="d-flex align-items-center justify-content-center" style="height:320px;">
                                        <i class="ti tabler-photo-off fs-1 text-secondary opacity-50"></i>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-dark rounded-circle position-absolute top-50 start-0 translate-middle-y ms-2" id="pdmPrev" style="display:none;padding:10px;opacity:.8;">
                                        <i class="ti tabler-chevron-left"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-dark rounded-circle position-absolute top-50 end-0 translate-middle-y me-2" id="pdmNext" style="display:none;padding:10px;opacity:.8;">
                                        <i class="ti tabler-chevron-right"></i>
                                    </button>
                                    <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2" id="pdmDots" style="display:none;"></div>
                                </div>
                                <div class="d-flex gap-2 mt-3 overflow-auto pb-1" id="pdmThumbs"></div>
                            </div>
                        </div>
                        <div class="col-lg-5 border-start">
                            <div class="p-4 d-flex flex-column h-100">
                                <div class="mb-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h4 class="mb-0" id="pdmNameSide"></h4>
                                        <span id="pdmOfficialBadge" style="display:none;" data-bs-toggle="tooltip" title="{{ __('Official') }}">
                                            <span class="badge bg-info d-inline-flex align-items-center"><i class="ti tabler-rosette-discount-check icon-xs"></i></span>
                                        </span>
                                    </div>
                                    <div class="text-muted small mb-1" id="pdmAuthorWrap">
                                        <i class="ti tabler-user-circle icon-xs me-1"></i>
                                        <span id="pdmAuthor"></span>
                                    </div>
                                    <div id="pdmWebsiteWrap" style="display:none;">
                                        <a id="pdmWebsiteLink" href="#" target="_blank" rel="noopener" class="text-muted small text-decoration-none d-inline-flex align-items-center gap-1">
                                            <i class="ti tabler-world icon-xs"></i>
                                            <span id="pdmWebsiteText"></span>
                                        </a>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mb-3" id="pdmBadges">
                                    <span class="badge bg-label-secondary d-inline-flex align-items-center" id="pdmVersion"></span>
                                    <span class="badge bg-label-info d-inline-flex align-items-center" id="pdmCompat"></span>
                                    <span class="badge bg-label-primary d-inline-flex align-items-center" id="pdmDownloads"></span>
                                    <span id="pdmProBadge" style="display:none;"></span>
                                    <span id="pdmLicenseBadge" style="display:none;"></span>
                                    <span id="pdmCategoryBadge" style="display:none;"></span>
                                </div>

                                <div class="mb-3">
                                    <h6 class="text-heading mb-2">{{ __('Description') }}</h6>
                                    <p class="text-muted small mb-0" id="pdmDesc"></p>
                                </div>

                                <div class="mb-3" id="pdmDateWrap">
                                    <div class="d-flex align-items-center text-muted small">
                                        <i class="ti tabler-calendar icon-xs me-1"></i>
                                        <span id="pdmDate"></span>
                                    </div>
                                </div>

                                <div id="pdmDonateWrap" style="display:none;" class="mt-auto pb-2">
                                    <a id="pdmDonateLink" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-warning w-100 d-inline-flex align-items-center justify-content-center gap-2">
                                        <i class="ti tabler-heart"></i>{{ __('Donate To The Developer') }}
                                    </a>
                                </div>
                                <div class="pt-2 border-top" id="pdmInstallWrap"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var perPage = 12;
            var currentPage = 1;
            var activeCategory = 'all';
            var container = document.getElementById('pluginsContainer');
            if (!container) return;

            var allCards = Array.from(container.querySelectorAll('.plugin-card'));
            var filteredCards = allCards.slice();
            var searchInput = document.getElementById('pluginSearch');
            var sortSelect = document.getElementById('pluginSort');
            var pagination = document.querySelector('#pluginPagination .pagination');
            var noResults = document.getElementById('noResults');
            var resultCount = document.getElementById('pluginResultCount');
            var categoryButtons = document.querySelectorAll('.category-btn');

            categoryButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    categoryButtons.forEach(function (b) {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-primary', 'active');
                    activeCategory = this.dataset.category;
                    applyFilters();
                });
            });

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
                } else {
                    sorted.sort(function (a, b) {
                        var cmp = (b.dataset.added || '').localeCompare(a.dataset.added || '');
                        if (cmp !== 0) return cmp;
                        return parseInt(b.dataset.index) - parseInt(a.dataset.index);
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

                filteredCards = allCards.slice();

                if (activeCategory !== 'all') {
                    filteredCards = filteredCards.filter(function (card) {
                        return card.dataset.category === activeCategory;
                    });
                }

                if (query.length >= 3) {
                    filteredCards = filteredCards.filter(function (card) {
                        return card.dataset.name.indexOf(query) !== -1 ||
                               card.dataset.slug.indexOf(query) !== -1 ||
                               card.dataset.description.indexOf(query) !== -1;
                    });
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

            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) {
                return new bootstrap.Tooltip(el);
            });

            var modalEl = document.getElementById('pluginDetailModal');
            var bsModal = new bootstrap.Modal(modalEl);
            var galleryImages = [];
            var galleryIndex = 0;

            function numberFormat(num) {
                return parseInt(num).toLocaleString();
            }

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function openPluginModal(cardEl) {
                var rawData = cardEl.dataset.plugin;
                var cleanJsonString = rawData.replace(/&quot;/g, '"');
                var plugin = JSON.parse(cleanJsonString);
                var isInstalled = cardEl.dataset.installed === '1';
                var isSame = cardEl.dataset.sameVersion === '1';

                document.getElementById('pdmName').textContent = plugin.name || plugin.slug;
                document.getElementById('pdmNameSide').textContent = plugin.name || plugin.slug;

                var officialBadge = document.getElementById('pdmOfficialBadge');
                if (plugin.official && plugin.official == '1') {
                    officialBadge.style.display = '';
                } else {
                    officialBadge.style.display = 'none';
                }

                var proBadge = document.getElementById('pdmProBadge');
                if (plugin.pro && plugin.pro == '1') {
                    if (plugin.pro_url) {
                        proBadge.innerHTML = '<a href="' + escapeHtml(plugin.pro_url) + '" target="_blank" rel="noopener" class="badge bg-danger d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="{{ __("Paid - License required") }}"><i class="ti tabler-currency-dollar icon-xs me-1"></i> {{ __("Pro") }}</a>';
                    } else {
                        proBadge.innerHTML = '<span class="badge bg-danger d-inline-flex align-items-center" data-bs-toggle="tooltip" title="{{ __("Paid - License required") }}"><i class="ti tabler-currency-dollar icon-xs me-1"></i> {{ __("Pro") }}</span>';
                    }
                    proBadge.style.display = '';
                } else {
                    proBadge.style.display = 'none';
                }

                var licenseBadge = document.getElementById('pdmLicenseBadge');
                if (plugin.license) {
                    if (plugin.license_url) {
                        licenseBadge.innerHTML = '<a href="' + escapeHtml(plugin.license_url) + '" target="_blank" rel="noopener" class="badge bg-label-secondary d-inline-flex align-items-center text-decoration-none" data-bs-toggle="tooltip" title="' + escapeHtml(plugin.license) + '"><i class="ti tabler-copyright text-danger icon-xs"></i></a>';
                    } else {
                        licenseBadge.innerHTML = '<span class="badge bg-label-secondary d-inline-flex align-items-center" data-bs-toggle="tooltip" title="' + escapeHtml(plugin.license) + '"><i class="ti tabler-copyright text-danger icon-xs"></i></span>';
                    }
                    licenseBadge.style.display = '';
                } else {
                    licenseBadge.style.display = 'none';
                }

                var categoryBadge = document.getElementById('pdmCategoryBadge');
                var cat = plugin._category || 'Others';
                categoryBadge.innerHTML = '<span class="badge bg-label-info d-inline-flex align-items-center"><i class="ti tabler-category icon-xs me-1"></i> ' + escapeHtml(cat) + '</span>';
                categoryBadge.style.display = '';

                var authorWrap = document.getElementById('pdmAuthorWrap');
                authorWrap.innerHTML = '<i class="ti tabler-user-circle icon-xs me-1"></i>' + escapeHtml(plugin.author || '{{ __("Unknown Author") }}');

                var websiteWrap = document.getElementById('pdmWebsiteWrap');
                var websiteLink = document.getElementById('pdmWebsiteLink');
                var websiteText = document.getElementById('pdmWebsiteText');
                if (plugin.website) {
                    websiteLink.href = plugin.website;
                    try {
                        websiteText.textContent = new URL(plugin.website).hostname;
                    } catch(e) {
                        websiteText.textContent = plugin.website;
                    }
                    websiteWrap.style.display = '';
                } else {
                    websiteWrap.style.display = 'none';
                }

                var donateWrap = document.getElementById('pdmDonateWrap');
                var donateLink = document.getElementById('pdmDonateLink');
                if (plugin.donation) {
                    donateLink.href = plugin.donation;
                    donateWrap.style.display = '';
                } else {
                    donateWrap.style.display = 'none';
                }

                document.getElementById('pdmVersion').innerHTML = '<i class="ti tabler-tag icon-xs me-1"></i> v' + escapeHtml(plugin.version || '?');
                document.getElementById('pdmCompat').innerHTML = '<i class="ti tabler-versions icon-xs me-1"></i> ' + escapeHtml(plugin.compatibility || '-');

                var dlEl = document.getElementById('pdmDownloads');
                if (plugin.download_num) {
                    dlEl.innerHTML = '<i class="ti tabler-download icon-xs me-1"></i> ' + numberFormat(plugin.download_num);
                    dlEl.style.display = '';
                } else {
                    dlEl.style.display = 'none';
                }

                document.getElementById('pdmDesc').textContent = plugin.description || '{{ __("No description available.") }}';

                var dateWrap = document.getElementById('pdmDateWrap');
                if (plugin.added_at) {
                    dateWrap.style.display = '';
                    document.getElementById('pdmDate').textContent = plugin.added_at;
                } else {
                    dateWrap.style.display = 'none';
                }

                galleryImages = [];
                if (plugin.screenshots && plugin.screenshots.length > 0) {
                    galleryImages = plugin.screenshots.slice();
                } else if (plugin.screenshot) {
                    galleryImages = [plugin.screenshot];
                }
                galleryIndex = 0;
                renderGallery();

                var installWrap = document.getElementById('pdmInstallWrap');
                if (plugin.download_url) {
                    if (isInstalled && isSame) {
                        installWrap.innerHTML = '<button class="btn btn-label-secondary w-100" disabled><i class="ti tabler-check me-2"></i>{{ __("Already Installed") }}</button>';
                    } else {
                        installWrap.innerHTML = '<form action="{{ route("admin.plugins.marketplace.install") }}" method="POST"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="download_url" value="' + escapeHtml(plugin.download_url) + '"><input type="hidden" name="slug" value="' + escapeHtml(plugin.slug || '') + '"><button type="submit" class="btn btn-sm w-100 ' + (isInstalled ? 'btn-warning' : 'btn-primary') + '"><i class="ti tabler-' + (isInstalled ? 'refresh' : 'download') + ' me-2"></i>' + (isInstalled ? '{{ __("Update") }}' : '{{ __("Install Plugin") }}') + '</button></form>';
                    }
                } else {
                    installWrap.innerHTML = '<button class="btn btn-sm btn-label-secondary w-100" disabled><i class="ti tabler-link-off me-2"></i>{{ __("No Download Link") }}</button>';
                }

                bsModal.show();

                setTimeout(function () {
                    var newTooltips = modalEl.querySelectorAll('[data-bs-toggle="tooltip"]');
                    newTooltips.forEach(function (el) {
                        new bootstrap.Tooltip(el);
                    });
                }, 100);
            }

            function renderGallery() {
                var mainImg = document.getElementById('pdmMainImg');
                var noImg = document.getElementById('pdmNoImg');
                var prevBtn = document.getElementById('pdmPrev');
                var nextBtn = document.getElementById('pdmNext');
                var dotsEl = document.getElementById('pdmDots');
                var thumbsEl = document.getElementById('pdmThumbs');

                thumbsEl.innerHTML = '';
                dotsEl.innerHTML = '';

                if (galleryImages.length === 0) {
                    mainImg.style.display = 'none';
                    noImg.style.display = 'flex';
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                    dotsEl.style.display = 'none';
                    return;
                }

                noImg.style.display = 'none';
                mainImg.style.display = 'block';
                mainImg.src = galleryImages[galleryIndex];

                if (galleryImages.length > 1) {
                    prevBtn.style.display = 'flex';
                    nextBtn.style.display = 'flex';
                    dotsEl.style.display = 'flex';
                    dotsEl.className = 'position-absolute bottom-0 start-50 translate-middle-x mb-2 d-flex gap-1';

                    for (var d = 0; d < galleryImages.length; d++) {
                        var dot = document.createElement('span');
                        dot.style.cssText = 'width:8px;height:8px;border-radius:50%;cursor:pointer;' + (d === galleryIndex ? 'background:#fff;' : 'background:rgba(255,255,255,.5);');
                        dot.dataset.idx = d;
                        dot.addEventListener('click', function () {
                            galleryIndex = parseInt(this.dataset.idx);
                            renderGallery();
                        });
                        dotsEl.appendChild(dot);
                    }

                    for (var t = 0; t < galleryImages.length; t++) {
                        var thumb = document.createElement('img');
                        thumb.src = galleryImages[t];
                        thumb.style.cssText = 'width:64px;height:48px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid ' + (t === galleryIndex ? 'var(--bs-primary)' : 'rgba(0,0,0,.15)') + ';box-shadow:0 1px 4px rgba(0,0,0,.1);background:var(--bs-gray-200);';
                        thumb.dataset.idx = t;
                        thumb.addEventListener('click', function () {
                            galleryIndex = parseInt(this.dataset.idx);
                            renderGallery();
                        });
                        thumbsEl.appendChild(thumb);
                    }
                } else {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                    dotsEl.style.display = 'none';
                }
            }

            document.getElementById('pdmPrev').addEventListener('click', function () {
                if (galleryImages.length < 2) return;
                galleryIndex = (galleryIndex - 1 + galleryImages.length) % galleryImages.length;
                renderGallery();
            });

            document.getElementById('pdmNext').addEventListener('click', function () {
                if (galleryImages.length < 2) return;
                galleryIndex = (galleryIndex + 1) % galleryImages.length;
                renderGallery();
            });

            container.addEventListener('click', function (e) {
                if (e.target.closest('form') || e.target.closest('a[href]')) return;
                var card = e.target.closest('.plugin-card');
                if (card) openPluginModal(card);
            });
        });
    </script>
</x-layout-dashboard>
