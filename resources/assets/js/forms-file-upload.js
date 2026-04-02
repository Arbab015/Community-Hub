/**
 * File Upload
 */
'use strict';
(function () {
  // previewTemplate: Updated Dropzone default previewTemplate
  // ! Don't change it unless you really know what you are doing
  const previewTemplate = `<div class="dz-preview dz-file-preview">
<div class="dz-details">
  <div class="dz-thumbnail">
    <img data-dz-thumbnail>
    <span class="dz-nopreview">No preview</span>
    <div class="dz-success-mark"></div>
    <div class="dz-error-mark"></div>
    <div class="dz-error-message"><span data-dz-errormessage></span></div>
    <div class="progress">
      <div class="progress-bar progress-bar-primary" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-dz-uploadprogress></div>
    </div>
  </div>
  <div class="dz-filename" data-dz-name></div>
  <div class="dz-size" data-dz-size></div>
</div>
</div>`;

  // ? Start your code from here
  // Basic Dropzone (Single File - Main Picture)
  // --------------------------------------------------------------------
  const dropzoneBasic = document.querySelector('#dropzone-basic');
  let myDropzone = null;
  if (dropzoneBasic) {
    myDropzone = new Dropzone(dropzoneBasic, {
      previewTemplate: previewTemplate,
      parallelUploads: 1,
      maxFilesize: 20,
      addRemoveLinks: true,
      maxFiles: 1,
      autoProcessQueue: false,
      acceptedFiles: 'image/*',
      url: '/upload' // dummy URL
    });
  }

  // Multiple Dropzone (Multiple Documents)
  // --------------------------------------------------------------------
  const dropzoneMulti = document.querySelector('.dropzone_multi');
  const isRestricted = dropzoneMulti.getAttribute('isRestricted');
  let myDropzoneMulti = null;
  if (dropzoneMulti) {
    myDropzoneMulti = new Dropzone(dropzoneMulti, {
      previewTemplate: previewTemplate,
      parallelUploads: 20,
      maxFilesize: 10,
      maxFiles: 6,
      addRemoveLinks: true,
      autoProcessQueue: false,
      uploadMultiple: true,
      acceptedFiles: isRestricted ? 'image/*,video/*' : null,
      url: '/upload'
    });
  }

  // Handle form submission - Add files to hidden inputs before submit
  const create_society_form = document.querySelector('#create_society_form');
  const upload_media_form = document.querySelector('#upload_media_society');
  const property_form = document.querySelector('#property_form');
  let form = '';
  if (create_society_form) {
    form = create_society_form;
  }
  if (upload_media_form) {
    form = upload_media_form;
  }
  if (property_form){
    form = property_form;
  }
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // Remove existing hidden inputs if any
      const existingInputs = form.querySelectorAll(' input[name="documents[]"'); //input[name="main_pic"],
      existingInputs.forEach(input => input.remove());
      // Create DataTransfer to hold files
      const dataTransfer = new DataTransfer();
      // Add main picture if exists
      if (myDropzone && myDropzone.files.length > 0) {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'main_pic';
        fileInput.style.display = 'none';
        // Transfer the file
        dataTransfer.items.add(myDropzone.files[0]);
        fileInput.files = dataTransfer.files;
        form.appendChild(fileInput);
      }

      // Add multiple documents if they exist
      if (myDropzoneMulti && myDropzoneMulti.files.length > 0) {
        myDropzoneMulti.files.forEach(function (file, index) {
          const dataTransferMulti = new DataTransfer();
          const fileInput = document.createElement('input');
          fileInput.type = 'file';
          fileInput.name = 'documents[]';
          fileInput.style.display = 'none';

          // Transfer the file
          dataTransferMulti.items.add(file);
          fileInput.files = dataTransferMulti.files;
          form.appendChild(fileInput);
        });
      }
      // Now submit the form normally
      form.submit();
    });
  }
})();
