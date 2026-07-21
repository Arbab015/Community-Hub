<div class="modal fade" id="callForProperty" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">

      <div class="modal-header border-0 pb-0">
        <div class="w-100 text-center">
          <div
            class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-2"
            style="width:52px;height:52px;">
            <i class="ti tabler-phone text-primary fs-4"></i>
          </div>
          <h5 class="fw-bold mb-0">Call Owner</h5>
          <small class="text-muted">Tap the number to call or copy it</small>
        </div>
        <button type="button" class="btn-close position-absolute end-0 me-3 top-0 mt-3"
                data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body px-4 py-3">

        {{-- Phone --}}
        <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
          <div class="d-flex align-items-center gap-3">
            <i class="ti tabler-phone text-muted fs-5"></i>
            <div>
              <p class="text-muted mb-0" style="font-size:.75rem;">Phone</p>
              <a id="callModalNumber" href="#"
                 class="fw-bold text-decoration-none"
                 style="font-size:.95rem;"></a>
            </div>
          </div>
          <button onclick="copyContact('callModalNumber', 'copyFeedback', 'copyIcon')"
                  class="d-flex align-items-center gap-1 border-0 bg-transparent text-primary fw-semibold"
                  style="font-size:.85rem;">
            <i class="ti tabler-copy" id="copyIcon" style="font-size:1.1rem;"></i> Copy
          </button>
        </div>

        <p id="copyFeedback" class="text-primary small mt-2 mb-0 d-none text-center">
          <i class="ti tabler-check"></i> Copied to clipboard!
        </p>

        {{-- Reference note --}}
        <div class="text-center pt-3">
          <p class="text-muted mb-1" style="font-size:.8rem;">Please quote property reference</p>
          <p class="fw-bold mb-1" id="callModalRef" style="font-size:.85rem;"></p>
          <p class="text-muted mb-0" style="font-size:.8rem;">when calling us.</p>
        </div>

      </div>

    </div>
  </div>
</div>
