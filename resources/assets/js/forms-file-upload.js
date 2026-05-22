'use strict';
(function() {
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
      url: '/upload'
    });
  }

  function isImageFile(url) {
    if (!url) return false;
    return /\.(jpg|jpeg|png|gif|webp|svg|bmp|ico)(\?.*)?$/i.test(url);
  }

  if (myDropzone && window.existingMainPic) {
    let file = window.existingMainPic;
    if (file) {
      let mockFile = { name: file.name, size: convertToBytes(file.size), id: file.id, isMock: true };
      myDropzone.emit('addedfile', mockFile);
      if (file.url) myDropzone.emit('thumbnail', mockFile, file.url); // always image
      myDropzone.emit('complete', mockFile);
      myDropzone.files.push(mockFile);
    }
  }

  let deletedFiles = [];
  const dropzoneMulti = document.querySelector('.dropzone_multi');
  const isRestricted = dropzoneMulti?.getAttribute('isRestricted');
  let myDropzoneMulti = null;
  if (dropzoneMulti) {
    myDropzoneMulti = new Dropzone(dropzoneMulti, {
      previewTemplate: previewTemplate,
      parallelUploads: 20,
      maxFilesize: 20,
      addRemoveLinks: true,
      autoProcessQueue: false,
      uploadMultiple: true,
      acceptedFiles: isRestricted
        ? 'image/*,video/*'
        : '.jpg,.jpeg,.png,.gif,.svg,.mp4,.mov,.avi,.webm,.mkv,.pdf,.doc,.docx,.xls,.xlsx',
      url: '/upload'
    });
  }

  if (myDropzoneMulti && window.existingDocuments) {
    window.existingDocuments.forEach(function(file) {
      let mockFile = { name: file.name, size: convertToBytes(file.size), id: file.id, isMock: true };
      myDropzoneMulti.emit('addedfile', mockFile);
      // Only emit thumbnail for actual images — skip for pdf/doc/etc
      if (file.url && isImageFile(file.url)) {
        myDropzoneMulti.emit('thumbnail', mockFile, file.url);
      }
      myDropzoneMulti.emit('complete', mockFile);
      myDropzoneMulti.files.push(mockFile);
    });
  }

  const form = document.querySelector('#upload_media_property');
  const saveBtn = form?.querySelector('button[type="submit"], button:not([type])');
  const uploadBtn = document.getElementById('upload_files_btn');
  const saveDocumentsBtn = document.getElementById('save_documents_btn');

  function pollAttachmentProgress(attachmentIds, fileProgressMap, onAllDone) {
    const interval = setInterval(async function() {
      try {
        const res = await fetch('/attachments/progress?ids=' + attachmentIds.join(','));
        const data = await res.json();

        let allDone = true;
        attachmentIds.forEach(function(id) {
          const progress = data[id]?.progress ?? 0;
          const entry = fileProgressMap[id];
          if (!entry) return;

          entry.files.forEach(function(file) {
            file.upload = file.upload || {};
            file.upload.progress = progress;
            file.upload.total = file.size;
            file.upload.bytesSent = Math.round((progress / 100) * file.size);
            if (entry.dropzone) {
              entry.dropzone.emit('uploadprogress', file, progress, file.upload.bytesSent);
            }
          });

          if (progress !== 100 && progress !== -1) allDone = false;
        });

        if (allDone) {
          clearInterval(interval);
          if (typeof onAllDone === 'function') onAllDone(data);
        }
      } catch (e) {
        clearInterval(interval);
      }
    }, 1000);
  }

  if (form && uploadBtn) {

    function hasNewFiles() {
      const mainNew = myDropzone?.files.some(f => !f.isMock) ?? false;
      const multiNew = myDropzoneMulti?.files.some(f => !f.isMock) ?? false;
      return mainNew || multiNew;
    }

    function hasMockRemoved() {
      return deletedFiles.length > 0;
    }

    function checkUploadButtonVisibility() {
      const showUpload = hasNewFiles() || hasMockRemoved();

      if (showUpload) {
        uploadBtn.classList.remove('d-none');
        if (saveBtn) saveBtn.disabled = true;
        if (saveDocumentsBtn) saveDocumentsBtn.classList.add('disabled');
      } else {
        uploadBtn.classList.add('d-none');
        if (saveBtn) saveBtn.disabled = false;
        if (saveDocumentsBtn) saveDocumentsBtn.classList.remove('disabled');
      }
    }

    // Track deleted mock files — both dropzones
    myDropzone?.on('removedfile', function(file) {
      if (file.isMock && file.id) {
        deletedFiles.push(file.id);
      }
      checkUploadButtonVisibility();
    });

    myDropzoneMulti?.on('removedfile', function(file) {
      if (file.isMock && file.id) {
        deletedFiles.push(file.id);
      }
      checkUploadButtonVisibility();
    });

    myDropzone?.on('addedfile', function(f) {
      if (!f.isMock) checkUploadButtonVisibility();
    });

    myDropzoneMulti?.on('addedfile', function(f) {
      if (!f.isMock) checkUploadButtonVisibility();
    });

    uploadBtn.addEventListener('click', function() {
      const newFiles = myDropzoneMulti?.files.filter(f => !f.isMock) ?? [];
      const mainFile = myDropzone?.files.find(f => !f.isMock);

      const maxPerFile = 20;
      const maxTotal = 100;
      let totalMB = 0;
      const oversized = [];

      newFiles.forEach(f => {
        const mb = f.size / (1024 * 1024);
        totalMB += mb;
        if (mb > maxPerFile) oversized.push(f.name);
      });

      if (mainFile) {
        const mainMB = mainFile.size / (1024 * 1024);
        totalMB += mainMB;
        if (mainMB > maxPerFile) oversized.push(mainFile.name);
      }

      if (oversized.length) {
        notify(`These files exceed ${maxPerFile}MB each: ${oversized.join(', ')}`, 'error');
        return;
      }

      if (totalMB > maxTotal) {
        notify(`Total upload size exceeds ${maxTotal}MB. Please reduce files.`, 'error');
        return;
      }

      uploadBtn.disabled = true;
      uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Uploading...';

      const fd = new FormData(form);
      if (mainFile) fd.append('main_pic', mainFile);
      if (myDropzoneMulti) {
        myDropzoneMulti.files.forEach(f => {
          if (!f.isMock) fd.append('documents[]', f);
        });
      }
      fd.append('deleted_files', JSON.stringify(deletedFiles));

      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.upload.addEventListener('progress', function(e) {
        if (!e.lengthComputable) return;
        const pct = Math.round((e.loaded / e.total) * 100);
        uploadBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Uploading ${pct}%...`;
      });

      xhr.addEventListener('load', function() {
        if (xhr.status >= 200 && xhr.status < 400) {
          try {
            const res = JSON.parse(xhr.responseText);
            const attachmentIds = res.attachment_ids || [];

            let completeCalled = false;

            function convertUploadedFilesToMocks() {
              const mainFileFresh = myDropzone?.files.find(f => !f.isMock);
              const multiFiles = myDropzoneMulti?.files.filter(f => !f.isMock) ?? [];
              let multiIndex = 0;
              attachmentIds.forEach(function(id) {
                if (res.main_pic_id == id && mainFileFresh) {
                  mainFileFresh.isMock = true;
                  mainFileFresh.id = id;
                } else {
                  const currentFile = multiFiles[multiIndex];
                  if (currentFile) {
                    currentFile.isMock = true;
                    currentFile.id = id;
                    multiIndex++;
                  }
                }
              });
            }

            function onUploadComplete(hasError) {
              if (completeCalled) return;
              completeCalled = true;

              document.querySelectorAll('.dz-preview').forEach(p =>
                p.classList.add('dz-complete', hasError ? 'dz-error' : 'dz-success')
              );
              document.querySelectorAll('.progress').forEach(p => p.style.display = 'none');

              convertUploadedFilesToMocks();
              deletedFiles = [];
              uploadBtn.classList.add('d-none');
              uploadBtn.disabled = false;
              saveDocumentsBtn?.classList.remove('disabled');
              uploadBtn.innerHTML = '<i class="ti tabler-upload me-1"></i> Save Files';
              if (saveBtn) saveBtn.disabled = false;
              notify(hasError ? 'Some files failed to process.' : 'Files saved successfully.', hasError ? 'error' : 'success');

              const isGalleryPage = !!document.getElementById('upload_media_section');
              if (!hasError && isGalleryPage) {
                setTimeout(() => window.location.reload(), 1500);
              }
            }

            if (attachmentIds.length > 0) {
              const fileProgressMap = {};
              const mainFileFresh = myDropzone?.files.find(f => !f.isMock);
              const multiFiles = myDropzoneMulti?.files.filter(f => !f.isMock) ?? [];
              let multiIndex = 0;

              attachmentIds.forEach(function(id) {
                if (res.main_pic_id == id && mainFileFresh) {
                  fileProgressMap[id] = { dropzone: myDropzone, files: [mainFileFresh] };
                } else {
                  const currentFile = multiFiles[multiIndex];
                  if (currentFile) {
                    fileProgressMap[id] = { dropzone: myDropzoneMulti, files: [currentFile] };
                    multiIndex++;
                  }
                }
              });

              uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

              pollAttachmentProgress(attachmentIds, fileProgressMap, function(finalData) {
                const hasError = attachmentIds.some(id => finalData[id]?.progress === -1);
                onUploadComplete(hasError);
              });

            } else {
              myDropzone?.files.forEach(f => {
                if (!f.isMock) f.isMock = true;
              });
              myDropzoneMulti?.files.forEach(f => {
                if (!f.isMock) f.isMock = true;
              });
              onUploadComplete(false);
            }

          } catch (e) {
            window.location.href = xhr.responseURL;
          }
        } else {
          uploadBtn.disabled = false;
          uploadBtn.innerHTML = '<i class="ti tabler-upload me-1"></i> Save Files';
          notify('Upload failed. Please try again.', 'error');
        }
      });

      xhr.addEventListener('error', function() {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="ti tabler-upload me-1"></i> Upload Files';
        notify('Upload error. Please try again.', 'error');
      });

      xhr.send(fd);
    });
  }

  // Legacy form submit — societies and other forms
  const create_society_form = document.querySelector('#create_society_form');
  const upload_media_form = document.querySelector('#upload_media_society');
  let legacyForm = create_society_form || upload_media_form;

  if (legacyForm) {
    legacyForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const existingInputs = legacyForm.querySelectorAll('input[name="documents[]"]');
      existingInputs.forEach(input => input.remove());
      if (myDropzone?.files.length > 0) {
        const realFile = myDropzone.files.find(f => !f.isMock);
        if (realFile) {
          const fi = document.createElement('input');
          fi.type = 'file';
          fi.name = 'main_pic';
          fi.style.display = 'none';
          const dt = new DataTransfer();
          dt.items.add(realFile);
          fi.files = dt.files;
          legacyForm.appendChild(fi);
        }
      }
      if (myDropzoneMulti?.files.length > 0) {
        myDropzoneMulti.files.forEach(function(file) {
          if (file.isMock) return;
          const dt = new DataTransfer();
          const fi = document.createElement('input');
          fi.type = 'file';
          fi.name = 'documents[]';
          fi.style.display = 'none';
          dt.items.add(file);
          fi.files = dt.files;
          legacyForm.appendChild(fi);
        });
      }
      const di = document.createElement('input');
      di.type = 'hidden';
      di.name = 'deleted_files';
      di.value = JSON.stringify(deletedFiles);
      legacyForm.appendChild(di);
      legacyForm.submit();
    });
  }

  function convertToBytes(size) {
    if (!size) return 0;
    if (typeof size === 'number') return size;
    const value = parseFloat(size);
    const unit = size.replace(value, '').trim().toUpperCase();
    switch (unit) {
      case 'GB':
        return value * 1024 * 1024 * 1024;
      case 'MB':
        return value * 1024 * 1024;
      case 'KB':
        return value * 1024;
      default:
        return value;
    }
  }

})();
