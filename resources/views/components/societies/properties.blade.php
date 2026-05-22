@php
  $palette = ['color-blue','color-green','color-pink','color-amber','color-purple','color-coral'];
@endphp


<div class="card mb-4">
  <div class="card-header bg-white border-0 py-3 px-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">

      <!-- Society Info -->
      <div class="d-flex align-items-center gap-2">
        <h5 class="mb-1 fw-semibold text-dark">
          Blocks / Sectors
        </h5>
        <span class="badge bg-info-subtle text-info px-3 py-2 rounded-pill fw-semibold">
          <span class="me-1">Total Blocks / Sectors:  </span>   {{  $society->blocks->count() }}
        </span>
      </div>

      <!-- Action Button -->
      @can('add_block')
        <button
          class="btn btn-primary btn-sm d-flex align-items-center gap-2 px-3 py-2 shadow-sm"
          onclick="addBlockModel(event)">
          <i class="ti tabler-plus"></i>
          <span>Add New Block</span>
        </button>
      @endcan
    </div>
  </div>
  <hr class="my-0">
</div>

@can('listing_block')
  @if($society->blocks->count())
    <div class="row g-3">
      @foreach($society->blocks as $i => $block)
        @php $color = $palette[$i % count($palette)]; @endphp
        <div class="col-12 col-md-4 col-xxl-3">
          <div class="card shadow-sm block-card h-100 {{ $color }}">
            <div class="block-top-bar"></div>
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="block-icon-wrap">
                  <i class="ti tabler-building-community"></i>
                </div>
                <span class="badge bg-light text-secondary fw-normal">Society Block</span>
              </div>
              <h6 class="fw-semibold mb-1">{{ ucwords($block->name) }}</h6>
              <p class="text-muted block-label mb-2">Total Properties</p>
              <div class="d-flex align-items-center justify-content-between mt-2">
          <span class="block-count-badge">
            <i class="ti tabler-home"></i>
         {{ $block->properties->count() }} Properties
          </span>
                <div class="d-flex gap-1">
                  @can('delete_block')
                    <form action="{{ route('blocks.destroy', $block->uuid) }}"
                          method="POST"
                          class="d-inline"
                          onsubmit="confirmDelete(event)">
                      @csrf
                      @method('DELETE')
                      <button type="submit" id="delete_btn"
                              class="btn btn-xs btn-danger border fw-semibold rounded-3">
                        Delete
                      </button>
                    </form>
                  @endcan

                  @can('view_block')
                    <a href="{{ route('society.block.view', [$user_type, $society->uuid,$block->uuid]) }}"
                       class="btn btn-xs btn-primary border fw-semibold rounded-3" id="view_btn">
                      View
                    </a>
                  @endcan

                </div>

              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="card ">
      <div class="text-center py-6">
        <i class="ti tabler-building-skyscraper fs-1 not_found_icon"> </i>
        <h6 class="text-muted">No Blocks Found</h6>
      </div>
    </div>
  @endif
@endcan

@cannot('listing_block')
  <div class="card ">
    <div class="text-center py-6">
      <i class="ti tabler-building-skyscraper fs-1 not_found_icon"> </i>
      <h6 class="text-muted">No permission to access society blocks.</h6>
    </div>
  </div>
@endcannot
@include('_partials._modals.add_edit_blocks')

@push('styles')
  <style>
    /* Add to your app.css or a <style> block in the layout */
    .block-card {
      border: none;
      border-radius: 12px;
      overflow: hidden;
      transition: transform .2s, box-shadow .2s;
    }

    .block-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(0, 0, 0, .10) !important;
    }

    .block-top-bar {
      height: 4px;
      width: 100%;
    }

    .block-icon-wrap {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .block-icon-wrap i {
      font-size: 20px;
    }

    .block-count-badge {
      font-size: 12px;
      font-weight: 500;
      padding: 4px 12px;
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .block-label {
      font-size: 12px;
    }

    /* Color variants */
    .color-blue .block-top-bar {
      background: #378ADD;
    }

    .color-blue .block-icon-wrap {
      background: #E6F1FB;
    }

    .color-blue .block-icon-wrap i {
      color: #378ADD;
    }

    .color-blue .block-count-badge {
      background: #E6F1FB;
      color: #185FA5;
    }

    .color-green .block-top-bar {
      background: #1D9E75;
    }

    .color-green .block-icon-wrap {
      background: #E1F5EE;
    }

    .color-green .block-icon-wrap i {
      color: #1D9E75;
    }

    .color-green .block-count-badge {
      background: #E1F5EE;
      color: #0F6E56;
    }

    .color-pink .block-top-bar {
      background: #D4537E;
    }

    .color-pink .block-icon-wrap {
      background: #FBEAF0;
    }

    .color-pink .block-icon-wrap i {
      color: #D4537E;
    }

    .color-pink .block-count-badge {
      background: #FBEAF0;
      color: #993556;
    }

    .color-amber .block-top-bar {
      background: #BA7517;
    }

    .color-amber .block-icon-wrap {
      background: #FAEEDA;
    }

    .color-amber .block-icon-wrap i {
      color: #BA7517;
    }

    .color-amber .block-count-badge {
      background: #FAEEDA;
      color: #854F0B;
    }

    .color-purple .block-top-bar {
      background: #7F77DD;
    }

    .color-purple .block-icon-wrap {
      background: #EEEDFE;
    }

    .color-purple .block-icon-wrap i {
      color: #7F77DD;
    }

    .color-purple .block-count-badge {
      background: #EEEDFE;
      color: #534AB7;
    }

    .color-coral .block-top-bar {
      background: #D85A30;
    }

    .color-coral .block-icon-wrap {
      background: #FAECE7;
    }

    .color-coral .block-icon-wrap i {
      color: #D85A30;
    }

    .color-coral .block-count-badge {
      background: #FAECE7;
      color: #993C1D;
    }


  </style>

@endpush


@push('scripts')
  <script>
    function addBlockModel(e) {
      e.preventDefault();
      $('#block_id').val('');
      $('#name').val('');
      let modal = new bootstrap.Modal(document.getElementById('blockModal'));
      modal.show();
    }

  </script>

@endpush
