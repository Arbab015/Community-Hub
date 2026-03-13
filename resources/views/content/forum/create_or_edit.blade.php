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
  <h4 class="mb-1">{{ isset($post) ? 'Edit Post' : 'Create Post' }}</h4>
  <nav aria-label="breadcrumb" class="pt-2 pb-3">
    <ol class="breadcrumb breadcrumb-custom-icon">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard.analytics') }}">Home</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      @if(isset($user_type))
      <li class="breadcrumb-item">
        <a href="{{ route('societies.index', $user_type) }}">Societies</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ route('societies.show', [$user_type, $uuid]) }}">Soceity</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      @else
      <li class="breadcrumb-item">
        <a href="{{ route('posts.index', $type) }}">{{$type}}</a>
        <i class="breadcrumb-icon icon-base ti tabler-chevron-right align-middle icon-xs"></i>
      </li>
      @endif
      <li class="breadcrumb-item active">{{ isset($post) ? 'Post edit' : 'Post create' }}</li>
    </ol>

  </nav>
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
