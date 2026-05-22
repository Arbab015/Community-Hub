{{-- Dimensions --}}
<div class="card-body border-bottom p-4">
  @if(!isset($total_area))
    <div class="d-flex align-items-center gap-2 mb-4">
      <span
        class="badge bg-primary rounded-circle d-inline-flex align-items-center justify-content-center detail_section">03</span>
      <h5 class="mb-0 fw-bold">Property Dimensions</h5>
    </div>
  @endif
  <div class="form-repeater">
    <div data-repeater-list="dimensions">
      @php
        $dimItems = old('dimensions', isset($property) ? $property->dimensions->toArray() : [['name'=>'','size'=>'','unit'=>'']]);
      @endphp

      @foreach($dimItems as $i => $dimension)
        <div data-repeater-item class="dimensions">
          <div class="row g-3 mb-3 align-items-start rounded-3 mx-0">
            {{-- Side Name --}}
            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="ti tabler-ruler text-muted"></i>
                </span>
                <input type="text"
                       name="name"
                       value="{{ $dimension['name'] ?? '' }}"
                       class="form-control border-start-0 @error('dimensions.'.$i.'.name') is-invalid @enderror"
                       required
                       placeholder="e.g. Length, Width, Front, Right">
              </div>
              @error('dimensions.'.$i.'.name')
              <div class="text-danger small mt-1">
                <i class="ti tabler-alert-circle me-1"></i>{{ $message }}
              </div>
              @enderror
            </div>

            {{-- Size --}}
            {{-- Size --}}
            <div class="col-md-4">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
              <div class="input-group">
    <span class="input-group-text bg-light border-end-0">
      <i class="ti tabler-number text-muted"></i>
    </span>
                @php
                  // If unit exists, it's from DB — convert back to user unit
                  // If no unit, it's a new empty row — show blank
                  $val = !empty($dimension['unit'])
                      ? app()->make(\App\Http\Controllers\PropertiesController::class)->convertForUser($dimension['size'], $dimension['unit'])
                      : ($dimension['size'] ?? '');
                @endphp
                <input type="number"
                       name="size"
                       step="0.01"
                       value="{{ $val !== '' ? round($val, 2) : '' }}"
                       class="form-control border-start-0 @error('dimensions.'.$i.'.size') is-invalid @enderror"
                       required
                       placeholder="e.g. 40, 12.21">
              </div>
              @error('dimensions.'.$i.'.size')
              <div class="text-danger small mt-1">
                <i class="ti tabler-alert-circle me-1"></i>{{ $message }}
              </div>
              @enderror
            </div>

            {{-- Unit --}}
            <div class="col-md-3">
              <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
              <select name="unit"
                      class="form-select @error('dimensions.'.$i.'.unit') is-invalid @enderror"
                      required>
                <option value="" disabled {{ empty($dimension['unit']) ? 'selected' : '' }}>Select Unit</option>
                <option value="feet" {{ ($dimension['unit'] ?? '') == 'feet'        ? 'selected' : '' }}>Feet</option>
                <option value="meter" {{ ($dimension['unit'] ?? '') == 'meter'       ? 'selected' : '' }}>Meter</option>
                <option value="yard" {{ ($dimension['unit'] ?? '') == 'yard'        ? 'selected' : '' }}>Yard</option>
              </select>
              @error('dimensions.'.$i.'.unit')
              <div class="text-danger small mt-1">
                <i class="ti tabler-alert-circle me-1"></i>{{ $message }}
              </div>
              @enderror
            </div>

            {{-- Remove button --}}
            <div class="col-md-1 pt-4 mt-1">
              <button type="button" data-repeater-delete class="btn" title="Remove row">
                <i class="ti tabler-x icon-lg text-danger mt-4"></i>
              </button>
            </div>

          </div>
        </div>
      @endforeach

    </div>
    <button type="button" id="add_dimension" data-repeater-create class="btn btn-outline-primary btn-sm mt-1">
      <i class="ti tabler-plus me-1"></i> Add Dimension
    </button>
  </div>
</div>
