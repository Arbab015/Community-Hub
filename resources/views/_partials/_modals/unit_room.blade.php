@can('edit_property')
  <div class="modal fade" id="room_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="room_modal_title">
            <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
            Add Room
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form method="POST" action="{{ route('unit.store') }}" id="room_modal_form" class="room-form-repeater">
          @csrf
          <input type="hidden" name="unit_id" value="{{ $unit->id }}">
          <input type="hidden" name="section" value="add_room" id="room_modal_section">
          <input type="hidden" name="room_id" id="room_modal_room_id" value="">

          <div class="modal-body pt-2">

            {{-- Room Type --}}
            <div class="mb-3">
              <label class="form-label fw-semibold text-uppercase small">Room Type</label>
              <select name="room_type" id="room_modal_room_type" class="form-select" required>
                <option value="">-- Select Room Type --</option>
                @foreach($room_types as $rt)
                  <option value="{{ $rt->id }}">{{ ucwords($rt->title) }}</option>
                @endforeach
              </select>
            </div>
            {{-- Dimensions — shared component + repeater.js --}}
            <p class="text-muted text-uppercase fw-semibold mb-0 mt-1" style="font-size:.68rem;letter-spacing:.07em;">
              Dimensions
            </p>
            @include('components.property.dimensions', [
              'isUnitDetailsPage' => true,
              'property'          => null,
              'unit'              => null,
            ])


            {{-- Amenities --}}
            <p class="text-muted text-uppercase fw-semibold mb-2 mt-4" style="font-size:.68rem;letter-spacing:.07em;">
              Amenities
            </p>
            <div class="row g-2">
              @foreach($amenities as $a)
                <div class="col-sm-6 col-lg-4">
                  <div class="form-check">
                    <input class="form-check-input room-amenity-check" type="checkbox"
                           name="amenities[]" value="{{ $a->id }}"
                           id="rm_amenity_{{ $a->id }}">
                    <label class="form-check-label small" for="rm_amenity_{{ $a->id }}">
                      {{ ucwords($a->title) }}
                    </label>
                  </div>
                </div>
              @endforeach
            </div>

          </div>

          <div class="modal-footer border-0 d-flex flex-column pt-0 gap-1">
            <button type="button" class="btn btn-outline-secondary w-100 me-0"
                    data-bs-dismiss="modal">Cancel
            </button>
            <button type="submit" class="btn btn-primary w-100 ms-0">
              <i class="fa-solid fa-floppy-disk me-1"></i>
              <span id="room_modal_submit_label">Save Room</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endcan
