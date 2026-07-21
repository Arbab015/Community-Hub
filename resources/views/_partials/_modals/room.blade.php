{{-- MODAL: Add Room / Edit Room --}}
<div class="modal fade" id="room_modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom px-4 py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="room_modal_title">
          <span class="d-inline-block bg-primary rounded" style="width:3px;height:18px;"></span>
          Add Room
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="{{ route('property.store') }}" id="room_form">
        @csrf
        <input type="hidden" name="section" value="construction">
        <input type="hidden" name="request_type" value="property_const">
        <input type="hidden" name="room_id" id="room_modal_room_id" value="">
        <input type="hidden" name="floor_id" id="room_modal_floor_id" value="">
        <input type="hidden" name="unit_id" id="room_modal_unit_id" value="">
        <div class="modal-body px-4 py-3" id="room_modal_body">
          {{-- injected --}}
        </div>
        <div class="modal-footer border-top px-4 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-bold">
            <i class="fa-solid fa-floppy-disk me-1"></i> Save Room
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
