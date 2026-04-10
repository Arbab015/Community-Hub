<!-- Edit User Modal -->
<div class="modal fade" id="edit_society_info" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple ">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="text-center mb-6 ">
        <h4 class="mb-2 fw-bolder ">Edit Basic Information</h4>
      </div>
      <form id="editSocietyForm" method="POST" action="{{ route('society.store', [$user_type, $society->uuid]) }}">
        @csrf
        <div class="card-body ">
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label fw-bolder fw-medium required">Society Name</label>
              <input type="text" class="form-control" name="name" required
                value="{{ old('name', $society->name) }}">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bolder fw-medium required">Default Marla Size:</label>
              <input type="number" class="form-control" required name="marla_size" step="0.01"
                     value="{{ old('marla_size', $society->marla_size) }}">
            </div>

            <div class="col-12">
              <label class="form-label fw-bolder fw-medium required">Address</label>
              <textarea class="form-control" rows="2" required name="address">{{ old('address', $society->address) }}</textarea>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bolder fw-medium required">City</label>
              <input type="text" class="form-control" required name="city"
                value="{{ old('city', $society->city) }}">
            </div>
            @php
              $selectedCountry = old('country', $society->country ?? '');
            @endphp
            <div class="col-md-6 form-control-validation">
              <label class="form-label fw-bolder required" for="country">Country</label>
              <select id="country" name="country" class="form-select select2 @error('country') is-invalid @enderror"
                required data-allow-clear="true">
                <option value="">Select Society Country</option>
                <option value="Pakistan" {{ $selectedCountry == 'Pakistan' ? 'selected' : '' }}>Pakistan</option>
                <option value="Australia" {{ $selectedCountry == 'Australia' ? 'selected' : '' }}>Australia</option>
                <option value="Bangladesh" {{ $selectedCountry == 'Bangladesh' ? 'selected' : '' }}>Bangladesh
                </option>
                <option value="Belarus" {{ $selectedCountry == 'Belarus' ? 'selected' : '' }}>Belarus</option>
                <option value="Brazil" {{ $selectedCountry == 'Brazil' ? 'selected' : '' }}>Brazil</option>
                <option value="Canada" {{ $selectedCountry == 'Canada' ? 'selected' : '' }}>Canada</option>
                <option value="China" {{ $selectedCountry == 'China' ? 'selected' : '' }}>China</option>
                <option value="France" {{ $selectedCountry == 'France' ? 'selected' : '' }}>France</option>
                <option value="Germany" {{ $selectedCountry == 'Germany' ? 'selected' : '' }}>Germany</option>
                <option value="India" {{ $selectedCountry == 'India' ? 'selected' : '' }}>India</option>
                <option value="Indonesia" {{ $selectedCountry == 'Indonesia' ? 'selected' : '' }}>Indonesia
                </option>
                <option value="Israel" {{ $selectedCountry == 'Israel' ? 'selected' : '' }}>Israel</option>
                <option value="Italy" {{ $selectedCountry == 'Italy' ? 'selected' : '' }}>Italy</option>
                <option value="Japan" {{ $selectedCountry == 'Japan' ? 'selected' : '' }}>Japan</option>
                <option value="Korea" {{ $selectedCountry == 'Korea' ? 'selected' : '' }}>Korea, Republic of
                </option>
                <option value="Mexico" {{ $selectedCountry == 'Mexico' ? 'selected' : '' }}>Mexico</option>
                <option value="Philippines" {{ $selectedCountry == 'Philippines' ? 'selected' : '' }}>Philippines
                </option>
                <option value="Russia" {{ $selectedCountry == 'Russia' ? 'selected' : '' }}>Russian Federation
                </option>
                <option value="South Africa" {{ $selectedCountry == 'South Africa' ? 'selected' : '' }}>South
                  Africa
                </option>
                <option value="Thailand" {{ $selectedCountry == 'Thailand' ? 'selected' : '' }}>Thailand</option>
                <option value="Turkey" {{ $selectedCountry == 'Turkey' ? 'selected' : '' }}>Turkey</option>
                <option value="Ukraine" {{ $selectedCountry == 'Ukraine' ? 'selected' : '' }}>Ukraine</option>
                <option value="United Arab Emirates"
                  {{ $selectedCountry == 'United Arab Emirates' ? 'selected' : '' }}>
                  United Arab Emirates
                </option>
                <option value="United Kingdom" {{ $selectedCountry == 'United Kingdom' ? 'selected' : '' }}>
                  United Kingdom
                </option>
                <option value="United States" {{ $selectedCountry == 'United States' ? 'selected' : '' }}>
                  United States
                </option>
              </select>
              @error('country')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <!-- ACTIONS -->
          <div class="d-flex justify-content-end gap-2 mt-6">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-save me-1"></i> Save Changes
            </button>
          </div>
        </div>

      </form>

    </div>
  </div>
</div>
<!--/ Edit User Modal -->
