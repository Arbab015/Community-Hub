@php
  $nameAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][name]"  : '__PREFIX__[dimensions][__DIM__][name]';
  $sizeAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][size]"  : '__PREFIX__[dimensions][__DIM__][size]';
  $unitAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][unit]"  : '__PREFIX__[dimensions][__DIM__][unit]';
  $dimData  = $dim ?? [];

  // ── Modal mode: flat field names, pre-filled from Eloquent $dim object ──
  if (!empty($modalMode)) {
    $nameAttr = "dimensions[{$di}][name]";
    $sizeAttr = "dimensions[{$di}][size]";
    $unitAttr = "dimensions[{$di}][unit]";
  }
@endphp

@if(!empty($modalMode))
  {{-- Modal render: plain div, no <template> wrapper, model object pre-fill --}}
  <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 mx-0">
    <input type="hidden" name="dimensions[{{ $di }}][id]" value="{{ $dim->id ?? '' }}">
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
        <input type="text" name="{{ $nameAttr }}"
               value="{{ $dim->name ?? '' }}"
               required class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
        <input type="number" name="{{ $sizeAttr }}"
               value="{{ $dim->size ?? '' }}"
               step="0.01" required class="form-control border-start-0" placeholder="e.g 40, 12.21">
      </div>
    </div>
    <div class="col-10 col-md-3">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
      <select name="{{ $unitAttr }}" class="form-select" required>
        <option value="" disabled selected>Select Unit</option>
        <option value="inch" {{ ($dim->unit ?? '') == 'inch'  ? 'selected' : '' }}>Inch</option>
        <option value="feet" {{ ($dim->unit ?? '') == 'feet'  ? 'selected' : '' }}>Feet</option>
        <option value="meter" {{ ($dim->unit ?? '') == 'meter' ? 'selected' : '' }}>Meter</option>
        <option value="yard" {{ ($dim->unit ?? '') == 'yard'  ? 'selected' : '' }}>Yard</option>
      </select>
    </div>
    <div class="col-2 col-md-1 ps-0 pt-4 mt-1">
      <button type="button" class="btn btn-remove-dim ps-3" title="Remove">
        <i class="ti tabler-x icon-lg text-danger"></i>
      </button>
    </div>
  </div>
@elseif(isset($p))
  {{-- Blade old-data render --}}
  <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 mx-0">
    <div class="col-md-4 ">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
        <input type="text" name="{{ $nameAttr }}"
               value="{{ $dimData['name'] ?? '' }}"
               required class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
      </div>
      @isset($errBase)
        @error($errBase.'.dimensions.'.$di.'.name')
        <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
        @enderror
      @endisset
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
        <input type="number" name="{{ $sizeAttr }}"
               value="{{ $dimData['size'] ?? '' }}"
               step="0.01" required class="form-control border-start-0" placeholder="e.g 40, 12.21">
      </div>
      @isset($errBase)
        @error($errBase.'.dimensions.'.$di.'.size')
        <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
        @enderror
      @endisset
    </div>
    <div class="col-10 col-md-3">
      <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
      <select name="{{ $unitAttr }}" class="form-select" required>
        <option value="" disabled selected>Select Unit</option>
        <option value="inch" {{ ($dimData['unit'] ?? '') == 'inch'  ? 'selected' : '' }}>Inch</option>
        <option value="feet" {{ ($dimData['unit'] ?? '') == 'feet'  ? 'selected' : '' }}>Feet</option>
        <option value="meter" {{ ($dimData['unit'] ?? '') == 'meter' ? 'selected' : '' }}>Meter</option>
        <option value="yard" {{ ($dimData['unit'] ?? '') == 'yard'  ? 'selected' : '' }}>Yard</option>
      </select>
      @isset($errBase)
        @error($errBase.'.dimensions.'.$di.'.unit')
        <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
        @enderror
      @endisset
    </div>
    <div class="col-2 col-md-1 ps-0 pt-4 mt-1">
      <button type="button" class="btn btn-remove-dim ps-3" title="Remove">
        <i class="ti tabler-x icon-lg text-danger"></i>
      </button>
    </div>
  </div>
@else
  {{-- JS template --}}
  <template id="tpl-dim-row">
    <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 mx-0">
      <div class="col-md-4 ">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
          <input type="text" name="__PREFIX__[dimensions][__DIM__][name]"
                 value=""
                 required class="form-control border-start-0" placeholder="e.g. Length, Width, Front, Right">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Size</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0"><i class="ti tabler-number text-muted"></i></span>
          <input type="number" name="__PREFIX__[dimensions][__DIM__][size]"
                 value=""
                 step="0.01" required class="form-control border-start-0" placeholder="e.g 40, 12.21">
        </div>
      </div>
      <div class="col-10 col-md-3">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
        <select name="__PREFIX__[dimensions][__DIM__][unit]" class="form-select" required>
          <option value="" disabled selected>Select Unit</option>
          <option value="inch">Inch</option>
          <option value="feet">Feet</option>
          <option value="meter">Meter</option>
          <option value="yard">Yard</option>
        </select>
      </div>
      <div class="col-2 col-md-1 ps-0 pt-4 mt-1">
        <button type="button" class="btn btn-remove-dim ps-3" title="Remove">
          <i class="ti tabler-x icon-lg text-danger"></i>
        </button>
      </div>
    </div>
  </template>
@endif
