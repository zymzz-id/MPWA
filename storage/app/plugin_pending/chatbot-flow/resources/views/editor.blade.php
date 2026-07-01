<x-layout-dashboard title="{{__('Chatbot Flow')}}">
	
	@if (!session()->has('selectedDevice'))
	<div class="card shadow-sm border-0">
		<div class="alert alert-danger m-4">
			<div class="text-center">{{ __('Please select a device first') }}</div>
		</div>
	</div>
	@else
	<link rel="stylesheet" href="{{asset('css/chatbot-flow.css')}}?v={{config('app.version')}}">
	<meta name="flow-id" content="{{ $flow->id ?? '' }}">
	<div class="card mb-3 border-0 shadow-sm">
		<div class="card-body p-3">
			<div class="row g-3 align-items-end">
				<div class="col-auto">
					<a href="{{ route('chatbot-flow') }}" class="btn btn-icon btn-sm btn-label-secondary">
						<i class="ti tabler-arrow-left"></i>
					</a>
				</div>
				
				<div class="col-12 col-lg-2">
					<label class="form-label mb-1" for="flowName">{{ __('Flow Name') }}</label>
					<input type="text" id="flowName" class="form-control form-control-sm" placeholder="{{ __('Flow Name') }}" value="{{ $flow->name ?? '' }}">
				</div>

				<div class="col-12 col-lg-2 flow-topbar-keywords">
					<label class="form-label mb-1">{{ __('Keyword') }}</label>
					<div class="flow-keyword-wrap">
						<div id="keywordTokensFlow" class="d-flex flex-wrap gap-1 mb-1"></div>
						<input type="text" id="keywordInputFlow" class="form-control form-control-sm" placeholder="{{ __('Keyword + Enter') }}">
						<input type="hidden" id="keywordHiddenFlow" value="{{ $flow->keyword ?? '' }}">
					</div>
				</div>

				<div class="col-6 col-lg-1">
					<label class="form-label mb-1" for="flowTypeKeyword">{{ __('Type') }}</label>
					<select id="flowTypeKeyword" class="form-select form-select-sm">
						<option value="Equal" {{ ($flow->type_keyword ?? 'Equal') == 'Equal' ? 'selected' : '' }}>{{ __('Equal') }}</option>
						<option value="Contain" {{ ($flow->type_keyword ?? '') == 'Contain' ? 'selected' : '' }}>{{ __('Contains') }}</option>
					</select>
				</div>

				<div class="col-6 col-lg-1">
					<label class="form-label mb-1" for="flowReplyWhen">{{ __('Reply When') }}</label>
					<select id="flowReplyWhen" class="form-select form-select-sm">
						<option value="All" {{ ($flow->reply_when ?? 'All') == 'All' ? 'selected' : '' }}>{{ __('All') }}</option>
						<option value="Group" {{ ($flow->reply_when ?? '') == 'Group' ? 'selected' : '' }}>{{ __('Group') }}</option>
						<option value="Personal" {{ ($flow->reply_when ?? '') == 'Personal' ? 'selected' : '' }}>{{ __('Personal') }}</option>
					</select>
				</div>

				<div class="col-12 col-lg-auto d-flex gap-3 align-items-center pb-2">
					<div class="form-check form-switch mb-0">
						<input type="checkbox" class="form-check-input" id="flowIsRead" {{ ($flow->is_read ?? 0) ? 'checked' : '' }}>
						<label class="form-check-label" for="flowIsRead">{{ __('Read') }}</label>
					</div>
					<div class="form-check form-switch mb-0">
						<input type="checkbox" class="form-check-input" id="flowIsTyping" {{ ($flow->is_typing ?? 0) ? 'checked' : '' }}>
						<label class="form-check-label" for="flowIsTyping">{{ __('Typing') }}</label>
					</div>
					<div class="form-check form-switch mb-0">
						<input type="checkbox" class="form-check-input" id="flowIsQuoted" {{ ($flow->is_quoted ?? 0) ? 'checked' : '' }}>
						<label class="form-check-label" for="flowIsQuoted">{{ __('Quoted') }}</label>
					</div>
				</div>

				<div class="col-12 col-lg-1">
					<label class="form-label mb-1" for="flowDelay">{{ __('Delay') }}</label>
					<div class="input-group input-group-sm">
						<input type="number" id="flowDelay" class="form-control form-control-sm" min="0" max="30" value="{{ $flow->delay ?? 0 }}">
						<span class="input-group-text">{{ __('s') }}</span>
					</div>
				</div>

				<div class="col-12 col-lg ms-auto text-end">
					<button id="btnSaveFlow" class="btn btn-outline-primary btn-sm w-100">
						<i class="ti tabler-device-floppy me-1"></i>{{ __('Save') }}
					</button>
				</div>
			</div>
		</div>
	</div>

	<div class="flow-editor-wrapper">
		<div class="flow-sidebar">
			<div class="sidebar-title">{{ __('Node Palette') }}</div>
			<div class="node-item" draggable="true" data-node="trigger">
				<i class="ti tabler-flag-3 text-success"></i> {{ __('Trigger') }}
			</div>
			<div class="node-item" draggable="true" data-node="text_message">
				<i class="ti tabler-message text-primary"></i> {{ __('Text Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="button_message">
				<i class="ti tabler-click" style="color:#7c3aed;"></i> {{ __('Button Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="list_message">
				<i class="ti tabler-list" style="color:#e67e22;"></i> {{ __('List Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="media_message">
				<i class="ti tabler-photo" style="color:#0891b2;"></i> {{ __('Media Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="location_message">
				<i class="ti tabler-map-pin text-danger"></i> {{ __('Location Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="vcard_message">
				<i class="ti tabler-address-book text-success"></i> {{ __('VCard Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="sticker_message">
				<i class="ti tabler-sticker" style="color:#e91e63;"></i> {{ __('Sticker Message') }}
			</div>
			<div class="node-item" draggable="true" data-node="product_message">
				<i class="ti tabler-shopping-cart" style="color:#795548;"></i> {{ __('Product Message') }}
			</div>
		</div>

		<div class="flow-canvas-wrapper" id="canvasWrapper">
			<div class="flow-canvas" id="flowCanvas">
				<svg class="flow-svg" id="flowSvg"></svg>
			</div>
			<div class="flow-zoom-controls">
				<button id="zoomIn" data-bs-toggle="tooltip" title="Zoom In"><i class="ti tabler-plus"></i></button>
				<button id="zoomOut" data-bs-toggle="tooltip" title="Zoom Out"><i class="ti tabler-minus"></i></button>
				<button id="zoomReset" data-bs-toggle="tooltip" title="Reset"><i class="ti tabler-focus-2"></i></button>
			</div>
		</div>
	</div>

	<div class="modal fade node-config-modal" id="nodeConfigModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="nodeConfigTitle">{{ __('Configure Node') }}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body" id="nodeConfigBody"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
					<button type="button" class="btn btn-primary btn-sm" id="btnSaveNodeConfig">{{ __('Save') }}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="flow-context-menu" id="flowContextMenu" style="display:none;">
		<div class="ctx-item ctx-delete"><i class="ti tabler-trash text-danger"></i> {{ __('Delete') }}</div>
	</div>

	@if (Session::has('selectedDevice'))
		<input type="hidden" id="selectedDeviceId" value="{{ Session::get('selectedDevice.device_id') }}">
		<input type="hidden" id="selectedDeviceBody" value="{{ Session::get('selectedDevice.device_body') }}">
	@endif

	<script>
		var FLOW_DATA = {!! $flow ? json_encode($flow->flow_data) : 'null' !!};
		var FLOW_ID = '{{ $flow->id ?? '' }}';
		var CSRF_TOKEN = '{{ csrf_token() }}';
		var SAVE_URL = '{{ $flow ? route("chatbot-flow.update", $flow->id) : route("chatbot-flow.store") }}';
		var SAVE_METHOD = '{{ $flow ? "PUT" : "POST" }}';
		var LIST_URL = '{{ route("chatbot-flow") }}';
		var LFM_URL = '{{ route("fm.fm-button") }}';
		var LANG = {
			trigger: '{{ __("Trigger") }}',
			text_message: '{{ __("Text Message") }}',
			button_message: '{{ __("Button Message") }}',
			list_message: '{{ __("List Message") }}',
			media_message: '{{ __("Media Message") }}',
			location_message: '{{ __("Location Message") }}',
			vcard_message: '{{ __("VCard Message") }}',
			sticker_message: '{{ __("Sticker Message") }}',
			product_message: '{{ __("Product Message") }}',
			entry_point: '{{ __("Entry Point") }}',
			click_to_configure: '{{ __("Click to configure") }}',
			message: '{{ __("Message") }}',
			footer: '{{ __("Footer message *optional") }}',
			image: '{{ __("Image") }}',
			required: '{{ __("Required") }}',
			choose: '{{ __("Choose") }}',
			add_button: '{{ __("Add Button") }}',
			add_section: '{{ __("Add Section") }}',
			add_row: '{{ __("Add Row") }}',
			button_x: '{{ __("Button :x") }}',
			section: '{{ __("Section") }}',
			display_text: '{{ __("Display Text") }}',
			type: '{{ __("Type") }}',
			reply: '{{ __("Reply") }}',
			call: '{{ __("Call") }}',
			url: '{{ __("URL") }}',
			copy: '{{ __("Copy") }}',
			phone_number: '{{ __("Phone Number") }}',
			copy_text: '{{ __("Copy Text") }}',
			row_title: '{{ __("Row Title") }}',
			row_description: '{{ __("Row Description") }}',
			title_list: '{{ __("Title List") }}',
			name_list: '{{ __("Name List") }}',
			button_text: '{{ __("Button") }}',
			caption: '{{ __("Caption") }}',
			media_type: '{{ __("Media Type") }}',
			latitude: '{{ __("Latitude") }}',
			longitude: '{{ __("Longitude") }}',
			name: '{{ __("Name") }}',
			phone: '{{ __("Phone Number") }}',
			sticker_url: '{{ __("Sticker URL") }}',
			product_id: '{{ __("Product ID") }}',
			product_title: '{{ __("Product Title") }}',
			description: '{{ __("Description") }}',
			currency: '{{ __("Currency") }}',
			company_name: '{{ __("Company Name") }}',
			price: '{{ __("Price") }}',
			old_price: '{{ __("Old Price") }}',
			maximal_4_button: '{{ __("Maximal 4 button") }}',
			saved: '{{ __("Flow saved successfully") }}',
			fill_name: '{{ __("Please fill flow name") }}',
			fill_keyword: '{{ __("Please fill keyword") }}',
			view_once: '{{ __("View Once") }}',
			configure: '{{ __("Configure") }}',
			delete_node: '{{ __("Delete") }}'
		};
	</script>
	<script src="{{asset('js/chatbot-flow-editor.js')}}?v={{config('app.version')}}"></script>
	@endif
</x-layout-dashboard>