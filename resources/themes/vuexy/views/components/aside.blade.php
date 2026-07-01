@php
    $numbers = request()->user()->devices()->latest()->get();
@endphp
	<!-- Menu -->
        <aside id="layout-menu" class="layout-menu menu-vertical menu">
          <div class="app-brand demo">
            <a href="{{url('/')}}" class="app-brand-link">
              <span class="app-brand-logo demo">
                <span class="text-primary">
                  <x-logo></x-logo>
                </span>
              </span>
            </a>

            <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
              <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
              <i class="icon-base ti tabler-x d-block d-xl-none"></i>
            </a>
          </div>

          <div class="menu-inner-shadow"></div>

          <ul class="menu-inner py-1">
            <!-- Dashboards -->
			@if(env("ENABLE_INDEX") == 'yes')
			<li class="menu-item">
              <a href="{{ route('index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-smart-home"></i>
                <div data-i18n="Home">{{__('Home')}}</div>
              </a>
            </li>
			@endif
			<li class="menu-item {{ request()->routeIs('home') ? 'active' : '' }}">
              <a href="{{ route('home') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-dashboard"></i>
                <div data-i18n="Dashboard">{{__('Dashboard')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('devices') ? 'active' : '' }}">
              <a href="{{ route('devices') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-device-mobile"></i>
                <div data-i18n="Devices">{{__('Devices')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('file-manager') ? 'active' : '' }}">
              <a href="{{ route('file-manager') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-cloud-up"></i>
                <div data-i18n="File Manager">{{__('File Manager')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('phonebook') ? 'active' : '' }}">
              <a href="{{ route('phonebook') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-notebook"></i>
                <div data-i18n="Phone Book">{{__('Phone Book')}}</div>
              </a>
            </li>
			<li class="menu-item open">
              <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon icon-base ti tabler-report"></i>
                <div data-i18n="Reports">{{__('Reports')}}</div>
              </a>
              <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('campaigns') ? 'active' : '' }}">
                  <a href="{{ route('campaigns') }}" class="menu-link">
                    <div data-i18n="Campaign / Blast">{{__('Campaign / Blast')}}</div>
                  </a>
                </li>
                <li class="menu-item {{ request()->routeIs('messages.history') ? 'active' : '' }}">
                  <a href="{{ route('messages.history') }}" class="menu-link">
                    <div data-i18n="Messages History">{{__('Messages History')}}</div>
                  </a>
                </li>
              </ul>
            </li>
			<li class="menu-header small">
              <span class="menu-header-text" data-i18n="Device tools">{{__('Device tools')}}</span>
            </li>
			
			<x-select-device :numbers="$numbers"></x-select-device>
			
			@php $pluginNavItems = app(\App\Services\PluginNavRegistry::class)->all(); @endphp

		@if (Session::has('selectedDevice'))
			<li class="menu-item {{ request()->routeIs('campaign.create') ? 'active' : '' }}">
              <a href="{{ route('campaign.create') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-brand-campaignmonitor"></i>
                <div data-i18n="Create Campaign">{{__('Create Campaign')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('messagetest') ? 'active' : '' }}">
              <a href="{{ route('messagetest') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-mail"></i>
                <div data-i18n="Test Message">{{__('Single Message')}}</div>
              </a>
            </li>

			@foreach ($pluginNavItems as $navSection)
				@if (!empty($navSection['admin_only']))
					@continue
				@endif
				@if (!empty($navSection['requires_device']))
					@php
						try { $navHref = isset($navSection['route_name']) ? route($navSection['route_name']) : ($navSection['url'] ?? '#'); } catch (\Exception $e) { $navHref = '#'; }
					@endphp
					<li class="menu-item {{ request()->routeIs($navSection['route_pattern'] ?? '') ? 'active' : '' }}">
						<a href="{{ $navHref }}" class="menu-link">
							<i class="menu-icon icon-base ti {{ $navSection['icon'] ?? 'tabler-puzzle' }}"></i>
							<div>{{ __($navSection['label'] ?? '') }}</div>
						</a>
					</li>
				@endif
			@endforeach
		@endif

		@foreach ($pluginNavItems as $navSection)
			@if (!empty($navSection['admin_only']) || !empty($navSection['requires_device']))
				@continue
			@endif
			@php
				try { $navHref = isset($navSection['route_name']) ? route($navSection['route_name']) : ($navSection['url'] ?? '#'); } catch (\Exception $e) { $navHref = '#'; }
			@endphp
			<li class="menu-item {{ request()->routeIs($navSection['route_pattern'] ?? '') ? 'active' : '' }}">
				<a href="{{ $navHref }}" class="menu-link">
					<i class="menu-icon icon-base ti {{ $navSection['icon'] ?? 'tabler-puzzle' }}"></i>
					<div>{{ __($navSection['label'] ?? '') }}</div>
				</a>
			</li>
		@endforeach

			<li class="menu-header small">
              <span class="menu-header-text" data-i18n="Developers">{{__('Developers')}}</span>
            </li>
			<li class="menu-item {{ request()->routeIs('rest-api') ? 'active' : '' }}">
              <a href="{{ route('rest-api') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-api-app"></i>
                <div data-i18n="API Docs">{{__('API Docs')}}</div>
              </a>
            </li>

			@if (Auth::user()->level == 'admin')
			<li class="menu-header small">
              <span class="menu-header-text" data-i18n="Admin Panel">{{__('Admin Panel')}}</span>
            </li>
			<li class="menu-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
              <a href="{{ route('admin.settings') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-settings"></i>
                <div data-i18n="Setting Server">{{__('Setting Server')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('admin.manage-users') ? 'active' : '' }}">
              <a href="{{ route('admin.manage-users') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-users"></i>
                <div data-i18n="Manage User">{{__('Manage User')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('languages.index') ? 'active' : '' }}">
              <a href="{{ route('languages.index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-language"></i>
                <div data-i18n="Manage Languages">{{__('Manage Languages')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('admin.index.edit') ? 'active' : '' }}">
              <a href="{{ route('admin.index.edit') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-home-edit"></i>
                <div data-i18n="Manage Homepage">{{__('Manage Homepage')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('cronjob') ? 'active' : '' }}">
              <a href="{{ route('cronjob') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-metronome"></i>
                <div data-i18n="Cronjob">{{__('Cronjob')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('update') ? 'active' : '' }}">
              <a href="{{ route('update') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-progress-down"></i>
                <div data-i18n="Update">{{__('Update')}}</div>
              </a>
            </li>
			<li class="menu-item {{ request()->routeIs('admin.plugins.*') ? 'active' : '' }}">
              <a href="{{ route('admin.plugins.index') }}" class="menu-link">
                <i class="menu-icon icon-base ti tabler-puzzle"></i>
                <div data-i18n="Plugins">{{__('Plugins')}}</div>
              </a>
            </li>

			@foreach ($pluginNavItems as $navSection)
				@if (empty($navSection['admin_only']))
					@continue
				@endif
				@php
					try { $navHref = isset($navSection['route_name']) ? route($navSection['route_name']) : ($navSection['url'] ?? '#'); } catch (\Exception $e) { $navHref = '#'; }
				@endphp
				<li class="menu-item {{ request()->routeIs($navSection['route_pattern'] ?? '') ? 'active' : '' }}">
					<a href="{{ $navHref }}" class="menu-link">
						<i class="menu-icon icon-base ti {{ $navSection['icon'] ?? 'tabler-puzzle' }}"></i>
						<div>{{ __($navSection['label'] ?? '') }}</div>
					</a>
				</li>
			@endforeach
			@endif
          </ul>
        </aside>

        <div class="menu-mobile-toggler d-xl-none rounded-1">
          <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large text-bg-secondary p-2 rounded-1">
            <i class="ti tabler-menu icon-base"></i>
            <i class="ti tabler-chevron-right icon-base"></i>
          </a>
        </div>
        <!-- / Menu -->