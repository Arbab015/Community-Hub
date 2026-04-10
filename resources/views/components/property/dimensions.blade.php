{{--   Dimensions  --}}
<div class="card-body border-bottom p-4">
  <div class="d-flex align-items-center gap-2 mb-4">
    <span class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">03</span>
    <h5 class="mb-0 fw-bold">Dimensions</h5>
  </div>

  <div class="form-repeater">
    <div data-repeater-list="dimensions">
      @if(old('dimensions', isset($property) ? $property->dimensions->toArray() : []))
        @foreach(old('dimensions', isset($property) ? $property->dimensions->toArray() : []) as $dimension)
          <div data-repeater-item>
            <div class="row g-3 mb-3 align-items-end rounded-3 mx-0">
              <div class="col-md-4">
                <label class="form-label fw-semibold small text-uppercase text-muted required">
                  Side Name
                </label>
                <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="ti tabler-ruler text-muted"></i>
                </span>
                  <input type="text" name="name"
                         value="{{ $dimension['name'] ?? '' }}"
                         class="form-control border-start-0"
                         required placeholder="e.g. Length, Width, Front, Right">
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold small text-uppercase text-muted required">
                  Size
                </label>
                <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="ti tabler-number text-muted"></i>
                </span>
                  <input type="number" name="size" step="0.01"
                         value="{{ $dimension['size'] ?? '' }}"
                         class="form-control border-start-0"
                         required placeholder="e.g. 40, 12.21">
                </div>
              </div>

              <div class="col-md-3">
                <label class="form-label fw-semibold small text-uppercase text-muted required">
                  Unit
                </label>
                <select name="unit" class="form-select" required>
                  <option value="" disabled>Select Unit</option>
                  <option value="feet" {{ ($dimension['unit'] ?? '') == 'feet' ? 'selected' : '' }}>Feet</option>
                  <option value="square_feet" {{ ($dimension['unit'] ?? '') == 'square_feet' ? 'selected' : '' }}>Square Feet</option>
                  <option value="meter" {{ ($dimension['unit'] ?? '') == 'meter' ? 'selected' : '' }}>Meter</option>
                  <option value="yard" {{ ($dimension['unit'] ?? '') == 'yard' ? 'selected' : '' }}>Yard</option>
                  <option value="marla" {{ ($dimension['unit'] ?? '') == 'marla' ? 'selected' : '' }}>Marla</option>
                  <option value="kanal" {{ ($dimension['unit'] ?? '') == 'kanal' ? 'selected' : '' }}>Kanal</option>
                </select>
              </div>

              <div class="col-md-1">
                <button type="button" data-repeater-delete
                        class="btn"
                        title="Remove row">
                  <i class="ti tabler-x icon-lg text-danger"></i>
                </button>
              </div>
            </div>
          </div>
        @endforeach
      @else

        {{-- Default empty row (NO CHANGE) --}}
        <div data-repeater-item>
          <div class="row g-3 mb-3 align-items-end rounded-3  mx-0">

            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">
                Side Name
              </label>
              <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="ti tabler-ruler text-muted"></i>
              </span>
                <input type="text" name="name"
                       class="form-control border-start-0"
                       required placeholder="e.g. Length, Width, Front, Right">
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">
                Size
              </label>
              <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="ti tabler-number text-muted"></i>
              </span>
                <input type="number" name="size" step="0.01" class="form-control border-start-0" required placeholder="e.g. 40, 12.21">
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold small text-uppercase text-muted required">
                Unit
              </label>
              <select name="unit" class="form-select" required>
                <option value="" disabled selected>Select Unit</option>
                <option value="feet">Feet</option>
                <option value="square_feet">Square Feet</option>
                <option value="meter">Meter</option>
                <option value="yard">Yard</option>
                <option value="marla">Marla</option>
                <option value="kanal">Kanal</option>
              </select>
            </div>

            <div class="col-md-1">
              <button type="button" data-repeater-delete
                      class="btn"
                      title="Remove row">
                <i class="ti tabler-x icon-lg text-danger"></i>
              </button>
            </div>
          </div>
        </div>
      @endif
    </div>
    <button type="button" data-repeater-create class="btn btn-outline-primary btn-sm mt-1">
      <i class="ti tabler-plus me-1"></i> Add Dimension
    </button>
  </div>
</div>
