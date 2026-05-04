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

  if (myDropzone && window.existingMainPic) {
    let file = window.existingMainPic;
    if (file){
      let mockFile = {
        name: file.name,
        size: convertToBytes(file.size),
        id: file.id,
        isMock: true
      };
      myDropzone.emit("addedfile", mockFile);
      if (file.url) {
        myDropzone.emit("thumbnail", mockFile, file.url);
      }
      myDropzone.emit("complete", mockFile);
      // IMPORTANT: push into Dropzone internal array
      myDropzone.files.push(mockFile);

      myDropzone.on("removedfile", function(file) {
        // only for existing files
        if (file.isMock && file.id) {
          deletedFiles.push(file.id);
        }
      });
    }
  }

  let deletedFiles = [];
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

    myDropzoneMulti.on("removedfile", function(file) {
      // only for existing files
      if (file.isMock && file.id) {
        deletedFiles.push(file.id);
      }
    });

  }
  if (myDropzoneMulti && window.existingDocuments) {
    console.log(window.existingDocuments);
    window.existingDocuments.forEach(function (file) {
      let mockFile = {
        name: file.name,
        size: convertToBytes(file.size),
        id: file.id,
        isMock: true
      };
      myDropzoneMulti.emit("addedfile", mockFile);
      if (file.url) {
        myDropzoneMulti.emit("thumbnail", mockFile, file.url);
      }
      myDropzoneMulti.emit("complete", mockFile);
      // IMPORTANT: push into Dropzone internal array
      myDropzoneMulti.files.push(mockFile);
    });
  }

  // Handle form submission - Add files to hidden inputs before submit
  const create_society_form = document.querySelector('#create_society_form');
  const upload_media_form = document.querySelector('#upload_media_society');
  const property_form = document.querySelector('#upload_media_property');
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
      const existingInputs = form.querySelectorAll(' input[name="documents[]"]');
      existingInputs.forEach(input => input.remove());
      // Create DataTransfer to hold files
      const dataTransfer = new DataTransfer();
      // Add main picture if exists
      if (myDropzone && myDropzone.files.length > 0) {
        const realFile = myDropzone.files.find(f => !f.isMock);
        if (realFile) {
          const fileInput = document.createElement('input');
          fileInput.type = 'file';
          fileInput.name = 'main_pic';
          fileInput.style.display = 'none';
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(realFile);
          fileInput.files = dataTransfer.files;
          form.appendChild(fileInput);
        }
      }

      // Add multiple documents if they exist
      if (myDropzoneMulti && myDropzoneMulti.files.length > 0) {
        myDropzoneMulti.files.forEach(function (file, index) {
          if(file.isMock){
            return;
          }
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
      // remove files on remove from UI
      const deletedInput = document.createElement('input');
      deletedInput.type = 'hidden';
      deletedInput.name = 'deleted_files';
      deletedInput.value = JSON.stringify(deletedFiles);
      form.appendChild(deletedInput);
      // Now submit the form normally
      form.submit();
    });
  }


  function convertToBytes(size) {
    if (!size) return 0;
    if (typeof size === 'number') return size;
    const value = parseFloat(size);
    const unit = size.replace(value, '').trim().toUpperCase();
    switch (unit) {
      case 'GB': return value * 1024 * 1024 * 1024;
      case 'MB': return value * 1024 * 1024;
      case 'KB': return value * 1024;
      default: return value;
    }
  }
})();


