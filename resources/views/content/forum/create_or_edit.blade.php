@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Analytics')
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/quill/katex.scss', 'resources/assets/vendor/libs/quill/editor.scss', 'resources/assets/vendor/libs/select2/select2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/quill/quill.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/forms-selects.js'])
@endsection

@section('content')
  <div
    class="d-flex align-items-center justify-content-between bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">

    <div>
      @unlessrole('Society Member')
      <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">
        Society Management
      </p>
      @endunlessrole

      <h4 class="text-dark fw-bold mb-2">
        {{ isset($post) ? 'Edit Post' : 'Create Post' }}
      </h4>

      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">

          <li class="breadcrumb-item">
            <a href="{{ route('dashboard.analytics') }}"
               class="text-dark opacity-75 text-decoration-none">
              Home
            </a>
          </li>

          @if(isset($user_type))

            <li class="breadcrumb-item">
              <a href="{{ route('societies.index', $user_type) }}"
                 class="text-dark opacity-75 text-decoration-none">
                Societies
              </a>
            </li>

            <li class="breadcrumb-item">
              <a href="{{ route('societies.show', [$user_type, $society->uuid]) }}"
                 class="text-dark opacity-75 text-decoration-none">
                Society
              </a>
            </li>

          @else

            <li class="breadcrumb-item">
              <a href="{{ route('posts.index', $type) }}"
                 class="text-dark opacity-75 text-decoration-none">
                {{ $type }}
              </a>
            </li>

          @endif

          <li class="breadcrumb-item active text-dark opacity-50">
            {{ isset($post) ? 'Post Edit' : 'Post Create' }}
          </li>

        </ol>
      </nav>
    </div>
    <i class="ti tabler-messages text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"></i>

  </div>

  @if (session('success'))
    <div class="alert alert-success"> {{ session('success') }} </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger"> {{ session('error') }} </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif
  @include('components.forum.create_edit')
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

      @if (isset($post) && $post->description)
      // Set existing description in Quill editor
      quill.root.innerHTML = {!! json_encode($post->description) !!};
      @endif

      const form = document.querySelector('#post_form');
      form.addEventListener('submit', function() {
        document.getElementById('description').value = quill.root.innerHTML;
      });
    });

    // slug on title
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    titleInput.addEventListener('input', function() {
      slugInput.value = makeSlug(this.value);
    });

    function makeSlug(text) {
      return text
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '') // remove special chars
        .replace(/\s+/g, '-') // spaces to dash
        .replace(/-+/g, '-'); // remove duplicate dash
    }
  </script>
@endpush
