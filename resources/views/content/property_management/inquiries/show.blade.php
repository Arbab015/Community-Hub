@extends('layouts/layoutMaster')
@section('title', 'Inquiry Details')
@section('content')

  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">

    <div>
      @if(!isset($isOwner))
        <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">
          {{ isset($society) && $user_type ? 'Society Management' : 'Property Management' }}
        </p>
      @endif
      <h4 class="mb-1">Property Inquiries</h4>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}" class="text-dark opacity-75 text-decoration-none">Home</a>
          </li>
          @if(!isset($isOwner))
            @if(isset($society) && $user_type)
              <li class="breadcrumb-item">
                <a href="{{ route('societies.index', $user_type) }}" class="text-dark opacity-75 text-decoration-none">Societies</a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                   class="text-dark opacity-75 text-decoration-none">Society</a>
              </li>
              @if($listing->listable_type === \App\Models\PropertyUnit::class)
                <li class="breadcrumb-item">
                  <a
                    href="{{ route('society.block.view', [$user_type, $society->uuid, $listing->listable->floor->property->block->uuid]) }}"
                    class="text-dark opacity-75 text-decoration-none">Block</a>
                </li>
                <li class="breadcrumb-item">
                  <a
                    href="{{ route('society.property.details', [$user_type, $society->uuid, $listing->listable->floor->property->uuid]) }}"
                    class="text-dark opacity-75 text-decoration-none">Property</a>
                </li>
                <li class="breadcrumb-item">
                  <a href="{{ route('society.unit.details', [$user_type, $society->uuid, $listing->listable->uuid]) }}"
                     class="text-dark opacity-75 text-decoration-none">Unit</a>
                </li>
              @else
                <li class="breadcrumb-item">
                  <a
                    href="{{ route('society.block.view', [$user_type, $society->uuid, $listing->listable->block->uuid]) }}"
                    class="text-dark opacity-75 text-decoration-none">Block</a>
                </li>
                <li class="breadcrumb-item">
                  <a
                    href="{{ route('society.property.details', [$user_type, $society->uuid, $listing->listable->uuid]) }}"
                    class="text-dark opacity-75 text-decoration-none">Property</a>
                </li>
              @endif
            @elseif(isset($block))
              <li class="breadcrumb-item">
                <a href="{{ route('blocks.index') }}" class="text-dark opacity-75 text-decoration-none">Blocks</a>
              </li>
              <li class="breadcrumb-item">
                <a href="{{ route('block.view', $block->uuid) }}"
                   class="text-dark opacity-75 text-decoration-none">Block</a>
              </li>
              <li class="breadcrumb-item">
                <a
                  href="{{ route('property.details', isset($property) ? $property->uuid : $unit->floor->property->uuid) }}"
                  class="text-dark opacity-75 text-decoration-none">
                  Property
                </a>
              </li>
              @if(isset($unit))
                <li class="breadcrumb-item">
                  <a href="{{ route('unit.details', $unit->uuid) }}"
                     class="text-dark opacity-75 text-decoration-none">Unit</a>
                </li>
              @endif
            @else
              <li class="breadcrumb-item">
                <a
                  href="{{ route('inquiry.index') }}"
                  class="text-dark opacity-75 text-decoration-none">Inquiries</a>
              </li>
            @endif
          @else
            <li class="breadcrumb-item">
              <a href="{{ route('owner.properties.show')  }}"
                 class="text-dark opacity-75 text-decoration-none">Properties</a>
            </li>
            <li class="breadcrumb-item">
              <a
                href="{{ route('property.details', isset($property) ? $property->uuid : $unit->floor->property->uuid) }}"
                class="text-dark opacity-75 text-decoration-none">
                Property
              </a>
            </li>
            @if(isset($unit))
              <li class="breadcrumb-item">
                <a href="{{ route('unit.details', $unit->uuid) }}"
                   class="text-dark opacity-75 text-decoration-none">Unit</a>
              </li>
            @endif
          @endif

          <li class="breadcrumb-item active text-dark opacity-50">Inquiries details</li>
        </ol>
      </nav>
    </div>
    <i class="ti tabler-home text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>
  </div>

  @if (session('success'))
    <div class="alert alert-success alert-dismissible d-flex align-items-center gap-2 fade show mb-3">
      <i class="ti tabler-circle-check-filled"></i> {{ session('success') }}
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger alert-dismissible d-flex align-items-center gap-2 fade show mb-3">
      <i class="ti tabler-alert-circle-filled"></i> {{ session('error') }}
      <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white border-bottom py-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
          <h5 class="fw-bold mb-0">{{ ucwords($propertyName)  }}</h5>
          <span class="badge {{ $listing?->listable_type === \App\Models\Property::class ? 'bg-primary' : 'bg-info' }}">
            {{ $listing?->listable_type === \App\Models\Property::class ? 'Property' : 'Unit' }}
          </span>
          @if($isSoldOut)
            <span class="badge bg-danger">
              <i class="ti tabler-shopping-cart-x me-1"></i>Sold Out
            </span>
          @endif
        </div>
        <div class="d-flex align-items-center gap-2">
          @if($canUnlinkOwner)
            <form action="{{ route('property_owners.unlink') }}" method="POST" onsubmit="return confirmDelete(event, null)">
              @csrf
              @method('DELETE')
              <input type="hidden" name="ownerable_type" value="{{ $ownerContext['ownerableType'] ?? '' }}">
              <input type="hidden" name="ownerable_id" value="{{ $ownerContext['ownerableId'] ?? '' }}">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="End this ownership and relist for sale">
                <i class="ti tabler-user-off me-1"></i><span class="d-none d-sm-inline">Unlink Owner</span>
              </button>
            </form>
          @endif
          @if($canClearInquiries)
            <form action="{{ route('inquiries.clear', $listing->uuid) }}" method="POST" onsubmit="return confirmDelete(event, null)">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-warning" title="Delete all inquiries for this listing">
                <i class="ti tabler-trash me-1"></i><span class="d-none d-sm-inline">Clear Inquiries</span>
              </button>
            </form>
          @endif
        </div>
      </div>
    </div>


    @if(isset($inquiries))
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-mail-filled text-primary icon-md"></i>
            <h6 class="fw-semibold mb-0">All Inquiries</h6>
            <span class="badge bg-label-primary rounded-pill inquiry-count">{{ $inquiries?->count() }}</span>
          </div>

          <div class="inquiry-search-wrap">
            <div class="input-group">
              <span class="input-group-text bg-white">
                <i class="ti tabler-search icon-sm text-primary"></i>
              </span>
              <input type="text" id="inquirySearch" class="form-control" placeholder="Search by name, phone or email..."
                     autocomplete="off">
              <button type="button" id="inquirySearchClear" class="btn btn-outline-secondary d-none"
                      title="Clear search">
                <i class="ti tabler-x icon-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div id="inquiryList">
          @forelse($inquiries as $inquiry)
            @php
              $itemMember = (isset($memberInquiryIds) && isset($memberInquiryIds[$inquiry->id])) ? $memberInquiryIds[$inquiry->id] : null;
              $itemPic = ($itemMember && $itemMember->attachment?->link) ? asset('storage/'.$itemMember->attachment->link) : null;
            @endphp
            <div class="rounded-3 border inquiry-item mb-3 overflow-hidden">
              <div class="d-flex align-items-center justify-content-between gap-3 p-3 bg-light border-bottom flex-wrap">
                <div class="d-flex align-items-center gap-3">
                  <div class="flex-shrink-0">
                    @if($itemPic)
                      <img src="{{ $itemPic }}" class="rounded-circle object-fit-cover" width="46" height="46"
                           alt="{{ $inquiry->name }}">
                    @else
                      <div
                        class="rounded-circle bg-label-primary d-flex align-items-center justify-content-center fw-bold"
                        style="width:46px;height:46px;font-size:1.1rem;">
                        {{ strtoupper(substr($inquiry->name, 0, 1)) }}
                      </div>
                    @endif
                  </div>
                  <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="fw-semibold fs-6">{{ ucwords($inquiry->name) }}</span>
                      @if($itemMember)
                        <span class="badge bg-label-primary">
                          <i class="ti tabler-user-check me-1"></i>Already Society Member
                        </span>
                      @endif
                    </div>
                    <div class="text-muted small">
                      <i class="ti tabler-clock icon-xs me-1"></i>{{ $inquiry->created_at->format('d M Y, h:i A') }}
                      <span class="mx-1">&middot;</span>{{ $inquiry->created_at->diffForHumans() }}
                    </div>
                  </div>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                  @unless($isSoldOut)
                    @if($itemMember)
                      <button type="button" class="btn btn-sm btn-outline-secondary assign-member-btn"
                              data-ownerable-type="{{ $ownerContext['ownerableType'] ?? '' }}"
                              data-ownerable-id="{{ $ownerContext['ownerableId'] ?? '' }}"
                              data-member-id="{{ $itemMember->id }}"
                              data-member-label="Linking {{ $itemMember->first_name }} {{ $itemMember->last_name }} as owner.">
                        <i class="ti tabler-link me-1"></i><span class="d-none d-sm-inline">Link As Owner</span>
                      </button>
                    @else
                      <button type="button" class="btn btn-sm btn-outline-primary create-member-btn"
                              data-ownerable-type="{{ $ownerContext['ownerableType'] ?? '' }}"
                              data-ownerable-id="{{ $ownerContext['ownerableId'] ?? '' }}"
                              data-prefill-name="{{ $inquiry->name }}"
                              data-prefill-email="{{ $inquiry->email }}"
                              data-prefill-contact="{{ $inquiry->contact_number }}">
                        <i class="ti tabler-user-plus me-1"></i><span class="d-none d-sm-inline">Create Member</span>
                      </button>
                    @endif
                  @endunless
                  <form action="{{ route('inquiries.dismiss', $inquiry->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event, null)">
                      <i class="ti tabler-trash"></i> <span class="d-none d-sm-inline ms-1">Delete</span>
                    </button>
                  </form>
                </div>
              </div>
              <div class="p-3">
                <div class="d-flex align-items-center gap-3 flex-wrap mb-3 pb-3 border-bottom">
                  <a href="mailto:{{ $inquiry->email }}"
                     class="d-flex align-items-center gap-2 text-dark text-decoration-none small">
                    <i class="ti tabler-mail icon-sm text-primary"></i>{{ $inquiry->email }}
                  </a>
                  <a href="tel:{{ $inquiry->contact_number }}"
                     class="d-flex align-items-center gap-2 text-dark text-decoration-none small">
                    <i class="ti tabler-phone icon-sm text-primary"></i>{{ $inquiry->contact_number }}
                  </a>
                </div>
                <div class="text-muted text-uppercase small fw-bold mb-2">
                  <i class="ti tabler-message-2 icon-xs me-2"></i>Message
                </div>
                <div class="rounded-2 p-3 border-start border-3 border-primary">
                  <p class="mb-0 text-dark"
                     style="white-space: pre-wrap; word-break: break-word;">{{ ucfirst($inquiry->message) }}</p>
                </div>
              </div>
            </div>
          @empty
            <div class="text-center py-5">
              <i class="ti tabler-mail-off fs-1 text-muted opacity-50 mb-3"></i>
              <h6 class="text-muted">No inquiries found for this listing</h6>
            </div>
          @endforelse
        </div>
      </div>
    @endif
  </div>

  @php
    $ownerableType = $ownerContext['ownerableType'] ?? '';
    $ownerableId = $ownerContext['ownerableId'] ?? '';
    $propertySocietyId = $ownerContext['propertySocietyId'] ?? '';
    $memberSocieties = $propertySocietyId
        ? \App\Models\Society::find($propertySocietyId)?->members()->with('attachment')->get()
        : collect();
    $ownerable = $listing?->listable;
    $ownerablePlan = $ownerable?->paymentPlan;
  @endphp
  @include('_partials._modals.create_member_owner', ['ownerableType' => $ownerableType, 'ownerableId' => $ownerableId, 'propertySocietyId' => $propertySocietyId, 'memberSocieties' => $memberSocieties, 'paymentPlan' => $ownerablePlan, 'planInstallments' => collect($ownerablePlan?->installmentAttachments ?? [])])

@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('inquirySearch');
      const searchClear = document.getElementById('inquirySearchClear');
      const listWrap = document.getElementById('inquiryList');
      const countBadge = document.querySelector('.inquiry-count');
      const searchUrl = "{{ route('inquiries.search', $listing->uuid) }}";
      let debounceTimer = null;

      function runSearch() {
        const q = searchInput.value.trim();

        if (q === '') {
          clearResults();
          return;
        }

        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
          .then(function(res) {
            return res.json();
          })
          .then(function(data) {
            listWrap.innerHTML = data.html;
            if (countBadge) {
              countBadge.textContent = data.count;
            }
          })
          .catch(function() {
          });
      }

      function clearResults() {
        searchClear.classList.add('d-none');
        if (searchInput.value.trim() === '') {
          window.location.reload();
        }
      }

      searchInput.addEventListener('input', function() {
        searchClear.classList.toggle('d-none', searchInput.value.trim() === '');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runSearch, 300);
      });

      searchClear.addEventListener('click', function() {
        searchInput.value = '';
        searchClear.classList.add('d-none');
        window.location.reload();
      });
    });
  </script>
@endpush

@push('styles')
  <style>
    .inquiry-item {
      transition: box-shadow .15s ease, border-color .15s ease;
    }

    .inquiry-item:hover {
      border-color: rgba(105, 108, 255, .3) !important;
      box-shadow: 0 2px 12px rgba(105, 108, 255, .08);
    }

    .ti.tabler-clock {
      vertical-align: middle;
      position: relative;
      top: -1px;
    }
  </style>
@endpush
