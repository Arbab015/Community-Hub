<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fa-solid fa-cloud-upload text-primary me-2"></i>
          Upload Media Files
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="upload_media_society"
            action="{{ isset($society) ? route('society.store', [$user_type, $society->uuid]) : route('property.store') }}"
        enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="request_type" value="media"></input>
        @if(isset($property))
          <input type="hidden" name="block_id" value="{{ $property->block->id }}">
          <input type="hidden" name="section" value="documents">
          <input type="hidden" name="property_id" value="{{ $property->id ?? '' }}">
        @endif
        <div class="modal-body">
          <div class="col-md-12">
            <label class="form-label fw-bolder required">Media Files</label>
            <div class="dropzone needsclick dz-clickable dropzone_multi" isRestricted="yes">
              <div class="dz-message needsclick">
                <i class="bx bx-upload p-0" style="font-size: 3rem; color: #999;"></i>
                <h6 class="m-0 pb-1">Drop files here or click to upload</h6>
                <span class="note needsclick text-muted d-block mt-0">(Upload multiple documents related to the
                  society)</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="uploadBtn">
            <i class="fa-solid fa-upload me-2"></i>Upload Files
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
