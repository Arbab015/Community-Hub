@extends('layouts/layoutMaster')

@section('title', 'Add Property')

@section('vendor-style')
  @vite([ 'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/tagify/tagify.scss' , 'resources/assets/vendor/libs/dropzone/dropzone.scss',])
@endsection

@section('vendor-script')
  @vite([
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/jquery-repeater/jquery-repeater.js', 'resources/assets/vendor/libs/tagify/tagify.js', 'resources/assets/vendor/libs/dropzone/dropzone.js',])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app-ecommerce-product-add.js', 'resources/assets/js/forms-file-upload.js'])
@endsection

@section('content')
  <h4 class="mb-1">Add Property</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('blocks.index') }}">Blocks</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('blocks.view' , $block->uuid ) }}">Blocks details</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Add Property</li>
    </ol>
  </nav>

  @if (session('success'))
    <div class="alert alert-warning">
      {{ session('success') }}
    </div>
  @endif

  {{-- Error Message (for general errors) --}}
  @if (session('error'))
    <div class="alert alert-danger">
      {{ session('error') }}
    </div>
  @endif


  <div class="card p-6">
    <h5 class="fw-bolder"> Add New Property </h5>
    <div class="alert alert-warning alert-dismissible" role="alert">
      <h5 class="alert-heading mb-1">Kindly consider the following guidelines for all property details</h5>
<span>
1) It is mandatory to select a <strong>Property Category</strong> for all property details before proceeding.<br>
2. The <strong>Property Type</strong> will be automatically assigned based on the selected category. Kindly choose the appropriate category to ensure accurate classification.<br>
3. Please provide complete and accurate <strong>dimensions for all sides</strong> of the property (e.g., length, width, right, front) to maintain precise and reliable data.<br>
4. If the construction of the property has been completed, please enable the <strong>"Construction Completed"</strong> toggle. This will allow you to enter additional relevant information.<br>
5. Ensure that all required fields are filled correctly to avoid incomplete or inconsistent property records.
</span>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>

    <form id="property_form" method="post" action="{{ route('property.store') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="block_id" value="{{ $block->id }}">
      <!-- Category Selection as Cards -->
      <div class="d-flex justify-content-between mb-4">
        <div class="category_card p-3 text-center border rounded cursor-pointer flex-fill me-2" data-category="residential">
          <i class="icon-base ti tabler-home icon-lg mb-2"></i>
          <h6 class="fw-semibold">Residential</h6>
          <p>Plots, Houses, and other residential properties.</p>
          <div class="radio-indicator mt-2">
            <input type="radio" name="category" value="residential" hidden>
            <span class="circle"></span>
          </div>
        </div>
        <div class="category_card p-3 text-center border rounded cursor-pointer flex-fill me-2" data-category="commercial">
          <i class="icon-base ti tabler-building icon-lg mb-2"></i>
          <h6 class="fw-semibold">Commercial</h6>
          <p>Plots, Buildings, Plazas, and other commercial properties.</p>
          <div class="radio-indicator mt-2">
            <input type="radio" name="category" value="commercial" hidden>
            <span class="circle"></span>
          </div>
        </div>
        <div class="category_card p-3 text-center border rounded cursor-pointer flex-fill" data-category="other">
          <i class="icon-base ti tabler-map-pin icon-lg mb-2"></i>
          <h6 class="fw-semibold">Other</h6>
          <p>Mosque, Temple, Hospital, Park, and more.</p>
          <div class="radio-indicator mt-2">
            <input type="radio" name="category" value="other" hidden>
            <span class="circle"></span>
          </div>
        </div>
      </div>

      <!-- Property Details -->
      <div class="row g-3">
        <div class="col-md-4 ">
          <label for="name" class="form-label fw-semibold required ">Property Name: </label>
          <input type="text" id="name" name="name" class="form-control @error('property_no') is-invalid @enderror" required placeholder="Enter Property Number">
          @error('property_no')
          <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>

        <div class="col-md-4 ">
          <label for="property_no" class="form-label fw-semibold required ">Property No: </label>
          <input type="text" id="property_no" name="property_no" class="form-control @error('property_no') is-invalid @enderror" required placeholder="Enter Property Number">
          @error('property_no')
          <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
        <div class="col-md-4">
          <label for="type" class="form-label fw-semibold required">Type: </label>
          <select id="type" name="type" class="form-control @error('type') is-invalid @enderror" required>
            <option value="" selected disabled>Select Type</option>
          </select>
          @error('type')
          <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
        <div class="col-md-4">
          <label for="address" class="form-label fw-semibold required">Address: </label>
          <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" required placeholder="Enter Address">
          @error('address')
          <div class="invalid-feedback d-block">{{ $message }}</div>
          @enderror
        </div>
      </div>



      <!-- Dimensions — div wrapper so jquery-repeater works (nested <form> is invalid HTML) -->
      <div class="mt-4">
        <h6 class="fw-bold mb-1">Dimensions:</h6>
        <div class="form-repeater">
          <div data-repeater-list="dimensions">
            <!-- Default row 1 -->
            <div data-repeater-item>
              <div class="row g-3 mb-3 align-items-end">
                <div class="col-md-4">
                  <label class="form-label fw-semibold required">Name: </label>
                  <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required placeholder="e.g. length, width">
                  @error('name')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold required">Size: </label>
                  <input type="number" name="size" step="0.00001" class="form-control @error('size') is-invalid @enderror" required placeholder="e.g. 40">
                  @error('size')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold required">Unit: </label>
                  <select name="unit" class="form-control @error('unit') is-invalid @enderror" required >
                    <option value="" disabled selected>Select Unit</option>
                    <option value="feet">feet</option>
                    <option value="square_feet">square_feet</option>
                    <option value="meter">meter</option>
                    <option value="yard">yard</option>
                    <option value="marla">marla</option>
                    <option value="kanal">kanal</option>
                  </select>
                  @error('unit')
                  <div class="invalid-feedback d-block">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-1">
                  <button type="button" class="btn text-danger" data-repeater-delete>
                    <i class="ti tabler-x icon-lg"></i>
                  </button>
                </div>
              </div>
            </div>

          </div>
          <div class="mt-4">
            <button type="button" class="btn btn-outline-primary btn-sm" data-repeater-create>
              <i class="icon-base ti tabler-plus icon-xs me-2"></i>
              Add another dimension
            </button>
          </div>
        </div>
      </div>

      <h6 class="fw-bold mt-4 mb-1">Documents:</h6>
      {{-- documents --}}
      <div class="col-md-12 mt-0">
        <label class="form-label fw-semibold ">Main Picture</label>
        <div class="dropzone needsclick dz-clickable @error('main_pic') is-invalid @enderror" id="dropzone-basic">
          <div class="dz-message needsclick">
            <i class="bx bx-upload" style="font-size: 3rem; color: #999;"></i>
            <h6 class="m-0">Drop files here or click to upload</h6>
            <span class="note needsclick text-muted d-block mt-0">(Upload main picture for the property)</span>
          </div>
        </div>
        @error('main_pic')
        <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
      </div>

      <!-- Related Documents - Multiple Files Upload -->
      <div class="col-md-12">
        <label class="form-label fw-semibold  mt-4">Related Documents</label>
        <div class="dropzone needsclick dz-clickable dropzone_multi @error('documents') is-invalid @enderror"
             id="dropzone-multi">
          <div class="dz-message needsclick">
            <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
            <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
            <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the
                    property)</span>
          </div>
        </div>
        @error('documents')
        <div class="text-danger mt-2">{{ $message }}</div>
        @enderror
      </div>


      <div class="d-flex justify-content-between ">
        <!-- is_constructed toggle -->
        <div class="mt-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_constructed" name="is_constructed" value="1">
            <label class="form-check-label fw-semibold" for="is_constructed">Construction Completed</label>
          </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
          <button type="submit" id="form-submit-btn" class="btn btn-primary">Submit  </button>
        </div>
      </div>

    </form>
  </div>

@endsection


@push('scripts')
  <!-- Category Selection Script -->
  <script>
    const categoryCards = document.querySelectorAll('.category_card');
    const typeSelect = document.getElementById('type');

    const types = {
      residential: ['Plot', 'House', 'Other'],
      commercial: ['Plot', 'Building', 'Plaza', 'Other'],
      other: ['Plot', 'Mosque', 'Temple', 'Hospital', 'Park', 'School', 'Govt-office', 'Other']
    };

    categoryCards.forEach(card => {
      card.addEventListener('click', () => {
        // Remove active from all
        categoryCards.forEach(c => c.classList.remove('border-primary', 'bg-light'));
        // Add active to clicked
        card.classList.add('border-primary', 'bg-light');
        // Check the hidden radio
        card.querySelector('input[type="radio"]').checked = true;
        // Populate Type Select
        const selectedCategory = card.dataset.category;
        typeSelect.innerHTML = '<option value="" selected disabled>Select Type</option>';
        types[selectedCategory].forEach(type => {
          const option = document.createElement('option');
          option.value = type.toLowerCase();
          option.text = type;
          typeSelect.appendChild(option);
        });
      });
    });

    // Manual category validation on submit (replaces hidden required)
    document.getElementById('property_form').addEventListener('submit', function (e) {
      const selected = document.querySelector('input[name="category"]:checked');
      if (!selected) {
        e.preventDefault();
        categoryCards.forEach(c => c.classList.add('border-danger'));
        return;
      }
      categoryCards.forEach(c => c.classList.remove('border-danger'));
    });

    document.getElementById('is_constructed').addEventListener('change', function () {
      document.getElementById('form-submit-btn').textContent = this.checked ?  'Submit and Next →' : 'Submit';
    });
  </script>


@endpush

@push('styles')
  <style>
    .category_card {
      transition: all 0.2s;
    }
    .category_card:hover {
      border-color: #6366f1;
      background-color: #f3f4ff;
    }
    .category_card .circle {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid #6366f1;
      border-radius: 50%;
    }
    .category_card input:checked + .circle {
      background-color: #6366f1;
    }
  </style>
@endpush
