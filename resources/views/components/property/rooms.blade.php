<div class="border rounded-3 mb-2 overflow-hidden shadow-sm">

  {{-- Room Header --}}
  <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
       style="background: linear-gradient(135deg, #f8f9ff 0%, #eef1fb 100%);">
    <span class="d-flex align-items-center gap-2 fw-semibold text-primary"
          style="font-size:.72rem; letter-spacing:.06em; text-transform:uppercase;">
      <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-circle"
            style="width:22px; height:22px;">
        <i class="ti tabler-door" style="font-size:.8rem;"></i>
      </span>
      Room
    </span>
    <button type="button" data-repeater-delete
            class="btn btn-sm d-inline-flex align-items-center gap-1 rounded-pill px-2 py-1"
            style="font-size:.7rem; color:#dc3545; background:rgba(220,53,69,.08); border:none;">
      <i class="ti tabler-trash" style="font-size:.75rem;"></i> Remove
    </button>
  </div>

  <div class="p-3">

    {{-- Room Type --}}
    <div class="row g-3 mb-3">
      <div class="col-md-5">
        <label class="form-label fw-semibold small text-uppercase text-muted required">Room Type</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="ti tabler-door text-muted"></i>
          </span>
          <select name="room_type" class="form-select border-start-0" required>
            <option value="" disabled selected>Select type</option>
            <option value="bedroom">Bedroom</option>
            <option value="guest_room">Guest Room</option>
            <option value="drawing_room">Drawing Room</option>
            <option value="dining_room">Dining Room</option>
            <option value="kitchen">Kitchen</option>
            <option value="bathroom">Bathroom</option>
            <option value="washroom">Washroom</option>
            <option value="store_room">Store Room</option>
            <option value="servant_quarter">Servant Quarter</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    {{-- Amenities --}}
    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Amenities</label>
    <div class="d-flex flex-wrap gap-3 mb-3">
      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
        <input class="form-check-input mt-0" type="checkbox" name="has_attached_bathroom" value="1">
        <i class="ti tabler-bath text-muted"></i>
        <span class="small">Attached Bathroom</span>
      </label>

      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
        <input class="form-check-input mt-0" type="checkbox" name="has_attached_ac" value="1">
        <i class="ti tabler-air-conditioning text-muted"></i>
        <span class="small">AC</span>
      </label>

      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
        <input class="form-check-input mt-0" type="checkbox" name="has_attached_balcony" value="1">
        <i class="ti tabler-building-arch text-muted"></i>
        <span class="small">Balcony</span>
      </label>

      <label class="d-flex align-items-center gap-2 border rounded-3 px-3 py-2 user-select-none" style="cursor:pointer;">
        <input class="form-check-input mt-0" type="checkbox" name="has_attached_wardrobe" value="1">
        <i class="ti tabler-hanger text-muted"></i>
        <span class="small">Wardrobe</span>
      </label>
    </div>

    {{-- Room Dimensions --}}
{{--    <label class="form-label fw-semibold small text-uppercase text-muted mb-2">Room Dimensions</label>--}}
{{--    @include('components.property.dimensions-inline')--}}

  </div>
</div>
