@php
  $nameAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][name]"  : '__PREFIX__[dimensions][__DIM__][name]';
  $sizeAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][size]"  : '__PREFIX__[dimensions][__DIM__][size]';
  $unitAttr = isset($p, $di) ? "{$p}[dimensions][{$di}][unit]"  : '__PREFIX__[dimensions][__DIM__][unit]';
  $dimData  = $dim ?? [];
@endphp

@isset($p)
  {{-- Blade old-data render --}}
  <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
    @else
      {{-- JS template --}}
      <template id="tpl-dim-row">
        <div class="dimension-row row g-3 mb-2 align-items-end rounded-3 p-2 mx-0">
          @endisset

          <div class="col-md-4">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Side Name</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-ruler text-muted"></i></span>
              <input type="text" name="{{ $nameAttr }}"
                     value="@isset($p){{ $dimData['name'] ?? '' }}@endisset"
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
                     value="@isset($p){{ $dimData['size'] ?? '' }}@endisset"
                     step="0.01" required class="form-control border-start-0" placeholder="e.g 40, 12.21">
            </div>
            @isset($errBase)
              @error($errBase.'.dimensions.'.$di.'.size')
              <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
              @enderror
            @endisset
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small text-uppercase text-muted required">Unit</label>
            <select name="{{ $unitAttr }}" class="form-select" required>
              <option value="" disabled selected>Select Unit</option>
              <option value="inch" @isset($p)
                {{ ($dimData['unit'] ?? '') == 'inch'  ? 'selected' : '' }}
                @endisset>Inch
              </option>
              <option value="feet" @isset($p)
                {{ ($dimData['unit'] ?? '') == 'feet'  ? 'selected' : '' }}
                @endisset>Feet
              </option>
              <option value="meter" @isset($p)
                {{ ($dimData['unit'] ?? '') == 'meter' ? 'selected' : '' }}
                @endisset>Meter
              </option>
              <option value="yard" @isset($p)
                {{ ($dimData['unit'] ?? '') == 'yard'  ? 'selected' : '' }}
                @endisset>Yard
              </option>
            </select>
            @isset($errBase)
              @error($errBase.'.dimensions.'.$di.'.unit')
              <div class="text-danger small mt-1"><i class="ti tabler-alert-circle me-1"></i>{{ $message }}</div>
              @enderror
            @endisset
          </div>
          <div class="col-md-1">
            <button type="button" class="btn btn-remove-dim" title="Remove">
              <i class="ti tabler-x icon-lg text-danger"></i>
            </button>
          </div>

          @isset($p)
        </div>
      @else
  </div>
  </template>
@endisset
