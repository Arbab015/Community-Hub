function confirmDelete(event , action = null) {
  event.preventDefault();
  const el = event.currentTarget;
  const form = el.closest('form');
  const href = el.getAttribute('href');
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: action === true ? 'Yes, approve it!' : action === false ? 'Yes, cancel it!' : 'Yes, delete it!'
  }).then(result => {
    if (!result.isConfirmed) return;
    // form delete
    if (form) {
      form.submit();
      return;
    }
    if (href) {
      window.location.assign(href);
    }
  });
}

$(document).on('change', '#select_all', function () {
  $('.checkbox').prop('checked', this.checked);
  toggleBulkDelete();
});

$(document).on('click', '.bulk_delete_btn', function () {
  let ids = [];
  let url = $(this).data('url');
  $('.checkbox:checked').each(function () {
    ids.push($(this).val());
  });
  Swal.fire({
    title: 'Are you sure?',
    text: 'This action cannot be undone!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete'
  }).then(result => {
    if (!result.isConfirmed) return;

    Swal.fire({
      title: 'Deleting...',
      allowOutsideClick: false,
      showConfirmButton: false,
      didOpen: () => Swal.showLoading()
    });
    $.ajax({
      url: url,
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        ids: ids
      },
      success: function () {
        Swal.fire('Deleted!', 'Users deleted successfully.', 'success');
        $('.table_to_reload').DataTable().ajax.reload(null, false);
        $('#select_all').prop('checked', false);
        toggleBulkDelete();
      },
      error: function () {
        Swal.fire('Error!', 'Something went wrong.', 'error');
      }
    });
  });
});

function toggleBulkDelete() {
  let checked = $('.checkbox:checked').length > 1;
  $('.bulk_delete_btn').toggleClass('d-none', !checked);
}
$(document).on('change', '.checkbox', toggleBulkDelete);
document.addEventListener('DOMContentLoaded', function () {
  [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));
});

// to update image on front end
function previewAvatar(event) {
  const input = event.target;
  const container = input.closest('div');
  const img = container.querySelector('img');
  const reader = new FileReader();
  reader.onload = function () {
    img.src = reader.result;
  };
  reader.readAsDataURL(input.files[0]);
}

let selectedFiles = [];

const trigger = document.getElementById('file_trigger');
const input = document.getElementById('documents_input');

trigger?.addEventListener('change', e => {
  const card = e.target.closest('.card');
  const card_body = card.querySelector('.card-body');

  // Add files
  selectedFiles = [...selectedFiles, ...Array.from(e.target.files)];
  updateInputFiles();
  renderFileList(card_body);

  trigger.value = ''; // reset
});

function renderFileList(card_body) {
  if (selectedFiles.length === 0) {
    card_body.querySelector('.file-counter')?.remove();
    document.getElementById('save_files_btn')?.classList.add('d-none');
    return;
  }
  console.log(card_body)
  let counter = card_body.querySelector('.file-counter');
  if (!counter) {
    counter = document.createElement('div');
    counter.className = 'file-counter d-flex flex-column gap-2 mb-2 px-3 py-2 rounded bg-label-info fw-medium';
    card_body.prepend(counter);
  }

  counter.innerHTML = '';

  const list = document.createElement('ul');
  list.className = 'mb-0 small w-100';

  selectedFiles.forEach((file, index) => {
    const div = document.createElement('div');
    div.className = 'd-flex justify-content-between align-items-center';

    const li = document.createElement('li');
    li.textContent = file.name;

    const remove = document.createElement('span');
    remove.className = 'ti tabler-x text-danger';
    remove.style.cursor = 'pointer';

    remove.addEventListener('click', () => {
      selectedFiles.splice(index, 1);
      updateInputFiles();
      renderFileList(card_body);
    });

    div.appendChild(li);
    div.appendChild(remove);
    list.appendChild(div);
  });

  counter.appendChild(list);
  // Show save button
  const save_btn = document.getElementById('save_files_btn');
  const bulk_btn = document.getElementById('bulk_btn');
  const bulkVisible = bulk_btn && !bulk_btn.classList.contains('d-none');

  if (selectedFiles.length > 0 && !bulkVisible) {
    save_btn?.classList.remove('d-none');
  } else {
    save_btn?.classList.add('d-none');
  }
}
function updateInputFiles() {
  const dataTransfer = new DataTransfer();
  selectedFiles.forEach(file => {
    dataTransfer.items.add(file);
  });
  input.files = dataTransfer.files;
}



// report model
let currentReportId = null;
function openReport(id, type) {
  currentReportId = id;
  document.getElementById('report_id').value = id;
  document.getElementById('report_type').value = type;
  // document.getElementById('reason_id').value = '';
  new bootstrap.Modal(document.getElementById('report_modal')).show();
}

let report_form = document.getElementById('reportForm');
if (report_form) {
  report_form.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('/report/store', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        const allIds = [currentReportId, ...(data.ids || [])];
        allIds.forEach(function (id) {
          // Remove post item
          $(`.post-item[data-id="${id}"]`).remove();
          // Remove comment item
          const commentItem = document.querySelector(`.comment-item[data-comment-id="${id}"]`);
          if (commentItem) {
            document.querySelector(`.replies-container-${id}`)?.remove();
            document.querySelector(`.see-more-replies[data-comment-id="${id}"]`)?.closest('div')?.remove();
            // If inside highlight block, remove whole block, else just the item
            const highlightBlock = commentItem.closest('.parent-reply-highlight');
            highlightBlock ? highlightBlock.remove() : commentItem.remove();
          }
          $(`.report_${id}`).hide();
          $(`.already_reported_${id}`).removeClass('d-none');
        });
        bootstrap.Modal.getInstance(document.getElementById('report_modal')).hide();
        Swal.fire('Success!', data.message, 'success');
      })
      .catch(err => {
        console.error(err);
        Swal.fire('Error!', err.message || 'Something went wrong', 'error');
      });
  });
}

function notify(message, type){
  notyf.open({
    duration: 3000,
    ripple: false,
    dismissible: true,
    position: {
      x: 'right',
      y: 'bottom'
    },
    type: type,
    message: message
  });
}
