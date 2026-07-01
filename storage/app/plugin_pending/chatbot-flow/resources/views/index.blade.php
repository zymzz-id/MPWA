<x-layout-dashboard title="{{__('Chatbot Flow')}}">
	<nav aria-label="breadcrumb">
		<ol class="breadcrumb breadcrumb-custom-icon">
			<li class="breadcrumb-item">
				<a href="javascript:void(0);">{{__('Whatsapp')}}</a>
				<i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
			</li>
			<li class="breadcrumb-item active">{{__('Chatbot Flow')}}</li>
		</ol>
	</nav>

	@if (session()->has('alert'))
		<x-alert>
			@slot('type', session('alert')['type'])
			@slot('msg', session('alert')['msg'])
		</x-alert>
	@endif

	@if (!session()->has('selectedDevice'))
		<div class="card shadow-sm border-0">
			<div class="alert alert-danger m-4">
				<div class="text-center">{{ __('Please select a device first') }}</div>
			</div>
		</div>
	@else
	<div class="card">
		<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
			<h5 class="card-title mb-0">
				{{ __('Chatbot Flow') }}
				@if (Session::has('selectedDevice'))
					<span class="text-muted small">({{ Session::get('selectedDevice')['device_body'] }})</span>
				@endif
			</h5>
			<div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
				<a href="{{ route('chatbot-flow.create') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
					<i class="ti tabler-plus me-1"></i> {{ __('New Flow') }}
				</a>
				<form method="GET" class="position-relative">
					<button type="submit" class="btn position-absolute top-50 start-0 translate-middle-y ps-3 pe-2 border-0 bg-transparent">
						<i class="ti tabler-search"></i>
					</button>
					<input type="text" name="keyword" class="form-control ps-5 pe-3" placeholder="{{ __('search') }}" value="{{ request()->get('keyword', '') }}">
				</form>
			</div>
		</div>
		<div class="card-body">
			@if ($flows->total() == 0)
				<div class="text-center py-5">
					<i class="ti tabler-git-branch text-muted" style="font-size: 4rem;"></i>
					<p class="text-muted mt-3">{{ __('No flows created yet') }}</p>
					<a href="{{ route('chatbot-flow.create') }}" class="btn btn-primary btn-sm mt-2">
						<i class="ti tabler-plus me-1"></i> {{ __('New Flow') }}
					</a>
				</div>
			@else
				<div class="row g-4">
					@foreach ($flows as $flow)
					<div class="col-md-6 col-lg-4" data-flow-id="{{ $flow->id }}">
						<div class="card border shadow-none h-100">
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start mb-3">
									<h6 class="card-title mb-0">{{ $flow->name }}</h6>
									<div class="form-check form-switch">
										<input type="checkbox" class="form-check-input toggle-flow-status" data-id="{{ $flow->id }}" {{ $flow->status == 'active' ? 'checked' : '' }}>
									</div>
								</div>
								<div class="mb-3">
									@foreach(explode(',', $flow->keyword) as $kw)
										<span class="badge bg-label-primary me-1 mb-1">{{ trim($kw) }}</span>
									@endforeach
								</div>
								<div class="d-flex gap-2 text-muted small mb-3">
									<span><i class="ti tabler-{{ $flow->type_keyword == 'Equal' ? 'equal' : 'text-wrap' }} me-1"></i>{{ __($flow->type_keyword) }}</span>
									<span><i class="ti tabler-users me-1"></i>{{ __($flow->reply_when) }}</span>
								</div>
								<div class="small text-muted">
									{{ $flow->created_at->diffForHumans() }}
								</div>
							</div>
							<div class="card-footer bg-transparent border-top d-flex justify-content-end gap-1">
								<a href="{{ route('chatbot-flow.edit', $flow->id) }}" class="btn btn-outline-primary btn-sm" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
									<i class="ti tabler-edit"></i>
								</a>
								<button type="button" class="btn btn-outline-danger btn-sm btn-delete-flow" data-id="{{ $flow->id }}" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
									<i class="ti tabler-trash"></i>
								</button>
							</div>
						</div>
					</div>
					@endforeach
				</div>
				<div class="row mx-3 mt-4 justify-content-between">
					{{ $flows->links('pagination::bootstrap-5') }}
				</div>
			@endif
		</div>
	</div>

	<div class="modal fade" id="deleteFlowModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-sm modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{__('Confirm Deletion')}}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">{{__('Are you sure you want to delete this flow?')}}</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{__('Cancel')}}</button>
					<button type="button" class="btn btn-outline-danger btn-sm" id="confirmDeleteFlow">{{__('Delete')}}</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.toggle-flow-status').forEach(function(el) {
			el.addEventListener('change', function() {
				var id = this.dataset.id;
				var status = this.checked ? 'active' : 'inactive';
				fetch('/chatbot-flow/' + id + '/status', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					body: JSON.stringify({ status: status })
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (window.notyf && !data.error) window.notyf.success(data.message);
					else if (window.notyf && data.error) window.notyf.error(data.message);
				});
			});
		});

		var deleteModal = new bootstrap.Modal(document.getElementById('deleteFlowModal'));
		var deleteFlowId = null;

		document.querySelectorAll('.btn-delete-flow').forEach(function(el) {
			el.addEventListener('click', function() {
				deleteFlowId = this.dataset.id;
				deleteModal.show();
			});
		});

		document.getElementById('confirmDeleteFlow').addEventListener('click', function() {
			if (!deleteFlowId) return;
			var formData = new FormData();
			formData.append('_method', 'delete');
			formData.append('id', deleteFlowId);
			fetch('{{ route("chatbot-flow.destroy") }}', {
				method: 'POST',
				headers: {
					'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
				},
				body: formData
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (!data.error) {
					if (window.notyf) window.notyf.success(data.message);
					var card = document.querySelector('[data-flow-id="' + deleteFlowId + '"]');
					if (card) card.remove();
				} else {
					if (window.notyf) window.notyf.error(data.message);
				}
			})
			.finally(function() {
				deleteModal.hide();
				deleteFlowId = null;
			});
		});
	});
	</script>
	@endif
</x-layout-dashboard>
