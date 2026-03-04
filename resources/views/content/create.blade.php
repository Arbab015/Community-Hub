{{-- @extends('layouts/layoutMaster')

@section('title', 'Add New User')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/typeahead-js/typeahead.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss', 'resources/assets/vendor/libs/quill/typography.scss', 'resources/assets/vendor/libs/highlight/highlight.scss', 'resources/assets/vendor/libs/quill/katex.scss', 'resources/assets/vendor/libs/quill/editor.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js', 'resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/typeahead-js/typeahead.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/quill/katex.js', 'resources/assets/vendor/libs/highlight/highlight.js', 'resources/assets/vendor/libs/quill/quill.js'])
@endsection


@section('content')
  <h4 class="mb-0">Create Posts</h4>
  <nav aria-label="breadcrumb " class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item active">Create </li>
    </ol>
  </nav>
  @if (session('success'))
    <div class="alert alert-success"> {{ session('success') }} </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger"> {{ session('error') }} </div>
  @endif

  <div class="row">
    <div class="col-12">
      <div class="card">
        <!-- Header with buttons -->
        <div class="card-header d-flex justify-content-between ">
          <div class="fw-bolder fs-5">Write a Post</div>
          <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="post_category" id="cat_discussion" value="discussion" checked>
            <label class="btn btn-outline-primary" for="cat_discussion">Discussions</label>

            <input type="radio" class="btn-check" name="post_category" id="cat_suggestion" value="suggestion">
            <label class="btn btn-outline-primary" for="cat_suggestion">Suggestions</label>

            <input type="radio" class="btn-check" name="post_category" id="cat_issue" value="issue">
            <label class="btn btn-outline-primary" for="cat_issue">Issues</label>
          </div>
        </div>

        <!-- Form -->
        <div class="card-body">
          <!-- Category description card -->
          <div class="alert alert-primary d-flex align-items-start" role="alert" id="category-card">
            <span class="alert-icon rounded my-5 ">
              <i class="icon-base ti tabler-message-circle icon-md w-px-30 "></i>
            </span>

            <div class="d-flex flex-column ps-3">
              <h5 class="alert-heading mb-1" id="category-title">
                Discussion
              </h5>
              <p class="mb-0" id="category-description">
                Open discussions are meant for meaningful conversations, questions, and idea sharing within the community.
                Use this space to connect with others, exchange thoughts, and explore topics that matter to your society.
              </p>
            </div>
          </div>


          <form method="post" id="post_form" action="{{ route('posts.store') }}">
            @csrf
            <!-- Hidden input for category -->
            <input type="hidden" name="category" id="category" value="discussion">
            <!-- Title -->
            <div class="form-control-validation mb-4">
              <label class="form-label-input fw-bolder required" for="title">Title</label>
              <input type="text" id="title" class="form-control @error('title') is-invalid @enderror" required
                placeholder="Title upto 300 characters" name="title" value="{{ old('title') }}" />
              @error('title')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <!-- Description -->
            <div class="form-control-validation mb-4">
              <label class="form-label-input fw-bolder required">Description</label>
              <div id="post-editor" class="border rounded"></div>
              <input type="hidden" name="description" id="description"
                class="form-control @error('description') is-invalid @enderror" required>
              @error('description')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
            <div class="d-flex justify-content-end">
              <button type="submit" class="btn btn-primary">Post Now</button>
            </div>


          </form>
        </div>
      </div>
    </div>
  </div>



@endsection
@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toolbar = [
        [{
          font: []
        }, {
          size: []
        }],
        ['bold', 'italic', 'underline', 'strike'],
        [{
          color: []
        }, {
          background: []
        }],
        [{
          script: 'sub'
        }, {
          script: 'super'
        }],
        [{
          header: '1'
        }, {
          header: '2'
        }, 'blockquote', 'code-block'],
        [{
          list: 'ordered'
        }, {
          indent: '-1'
        }, {
          indent: '+1'
        }],
        [{
          direction: 'rtl'
        }, {
          align: []
        }],
        ['link', 'image', 'video'],
        ['clean']
      ];

      const quill = new Quill('#post-editor', {
        theme: 'snow',
        placeholder: 'Write description about your post title.',
        modules: {
          toolbar: toolbar
        }
      });

      const form = document.querySelector('#post_form');
      form.addEventListener('submit', function() {
        document.getElementById('description').value = quill.root.innerHTML;
      });
    });

    const categoryInput = document.getElementById('category');
    const categoryTitle = document.getElementById('category-title');
    const categoryDescription = document.getElementById('category-description');
    const categoryCard = document.getElementById('category-card');
    const categoryData = {
      discussion: {
        title: "Discussion",
        description: "Open discussions are meant for meaningful conversations, questions, and idea sharing within the community. Use this space to connect with others, exchange thoughts, and explore topics that matter to your society.",
        alertClass: "alert-primary",
        icon: "tabler-message-circle"
      },
      suggestion: {
        title: "Suggestion",
        description: "Suggestions help improve the system or society by proposing new ideas, features, or better ways of doing things. Share your thoughts to help shape a more efficient and user-friendly community.",
        alertClass: "alert-success",
        icon: "tabler-bulb"
      },
      issue: {
        title: "Issue",
        description: "Issues are existing problems or challenges within the system or society that require attention and improvement. Report bugs, mismanagement, or concerns here so they can be reviewed and resolved properly.",
        alertClass: "alert-danger",
        icon: "tabler-alert-circle"
      }
    };

    document.querySelectorAll('input[name="post_category"]').forEach(radio => {
      radio.addEventListener('change', function() {
        const data = categoryData[this.value];
        categoryInput.value = this.value;
        categoryTitle.textContent = data.title;
        categoryDescription.textContent = data.description;
        categoryCard.className = `alert d-flex align-items-start ${data.alertClass}`;
        categoryCard.querySelector('i').className =
          `icon-base ti ${data.icon} icon-md w-px-30`;
      });
    });
  </script>
@endpush --}}
