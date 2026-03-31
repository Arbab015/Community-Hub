@php
  use Illuminate\Support\Facades\Auth;
@endphp

{{-- Right Section --}}
<div class="forum-right-sidebar">
  {{-- Create Post Button --}}
  @if ( ($society->status == 'active') || auth()->user()->hasRole('Society Owner'))
    <div class="card border-0 shadow-sm mb-5">
      <a href="{{ isset($user_type)
    ? route('posts.create_in_admin', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type]) : route('posts.create', ['type' => $type]) }}" class="btn btn-primary w-100">
        Create Post
      </a>
    </div>
  @endif

  <div class="row row-cols-1 row-cols-md-1 g-4 g-xl-0">
    {{-- My Contributions --}}
    <div class="col mb-xl-5">
      <div class="card border-0 shadow-sm h-100 position-sticky" style="top: 20px;">
        <div class="card-header bg-label-dark text-white py-2 mb-2">
          <h6 class="mb-0">Me </h6>
        </div>
        <div class="card-body px-3">
          <div class="d-flex mb-2">
            <img
              src="{{ Auth::user()->attachment?->link
                  ? asset('storage/' . Auth::user()->attachment->link)
                  : asset('assets/img/avatars/1.png') }}"
              class="rounded-circle" width="40" height="40" alt="User">
            <div class="ms-2">
              <h6 class="mb-0 fw-semibold"> {{ ucfirst(Auth()->user()->first_name) }}
                {{ ucfirst(Auth()->user()->last_name) }}</h6>
              <small class="text-muted"> {{ Auth()->user()->roles->pluck('name')->implode(', ') }}</small>
            </div>
          </div>

          <hr class="my-1">
          <a href="{{ isset($user_type)
              ? route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type, 'slug' => 'my_posts']) : route('my_posts.index', [$type, auth()->user()->uuid]) }}"
             class="d-block text-decoration-none mt-3 text-dark">
            <div class="d-flex justify-content-between align-items-center">
              <span>My posts</span>
              <i class="ti tabler-chevron-right"></i>
            </div>
          </a>
         @can('listing_block_post')
          <a href="{{  route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type , 'slug' => 'blocked_posts'])}}"
             class="d-block text-decoration-none mt-3 text-dark">
            <div class="d-flex justify-content-between align-items-center">
              <span>Blocked Posts</span>
              <i class="ti tabler-chevron-right"></i>
            </div>
          </a>
          @endcan
          @can('listing_requested_post')

{{--            <a href="{{ route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type , 'slug' => 'requested_posts']) }}"--}}
{{--               class="d-block text-decoration-none mt-3 text-dark">--}}
{{--              <div class="d-flex justify-content-between align-items-center">--}}
{{--                <span>Requested Posts</span>--}}
{{--                <i class="ti tabler-chevron-right"></i>--}}
{{--              </div>--}}
{{--            </a>--}}

          <a href="{{ route('requests.index', ['user_type' => $user_type, 'uuid' => $society->uuid] )}}"
             class="d-block text-decoration-none mt-3 text-dark">
            <div class="d-flex justify-content-between align-items-center">
              <span> Requested Posts </span>
              <i class="ti tabler-chevron-right"></i>
            </div>
          </a>

          @endcan
        </div>
      </div>
    </div>



    {{-- My Contributions --}}
    <div class="col mb-xl-5">
      <div class="card border-0 shadow-sm h-100 position-sticky" style="top: 20px;">
        <div class="card-header bg-label-dark text-white py-2 mb-2">
          <h6 class="mb-0">Me </h6>
        </div>
        <div class="card-body px-3">
          <div class="d-flex mb-2">
            <img
              src="{{ Auth::user()->attachment?->link
                  ? asset('storage/' . Auth::user()->attachment->link)
                  : asset('assets/img/avatars/1.png') }}"
              class="rounded-circle" width="40" height="40" alt="User">
            <div class="ms-2">
              <h6 class="mb-0 fw-semibold"> {{ ucfirst(Auth()->user()->first_name) }}
                {{ ucfirst(Auth()->user()->last_name) }}</h6>
              <small class="text-muted"> {{ Auth()->user()->roles->pluck('name')->implode(', ') }}</small>
            </div>
          </div>

          <hr class="my-1">
          <a href="{{ isset($user_type)
              ? route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type, 'slug' => 'my_posts']) : route('my_posts.index', [$type, auth()->user()->uuid]) }}"
             class="d-block text-decoration-none mt-3 text-dark">
            <div class="d-flex justify-content-between align-items-center">
              <span>My posts</span>
              <i class="ti tabler-chevron-right"></i>
            </div>
          </a>
          @can('listing_block_post')
            <a href="{{  route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type , 'slug' => 'blocked_posts'])}}"
               class="d-block text-decoration-none mt-3 text-dark">
              <div class="d-flex justify-content-between align-items-center">
                <span>Blocked Posts</span>
                <i class="ti tabler-chevron-right"></i>
              </div>
            </a>
          @endcan
          @can('listing_requested_post')
            {{--            <a href="{{ route('society.render_posts', ['user_type' => $user_type, 'uuid' => $society->uuid, 'type' => $type , 'slug' => 'requested_posts']) }}"--}}
            {{--               class="d-block text-decoration-none mt-3 text-dark">--}}
            {{--              <div class="d-flex justify-content-between align-items-center">--}}
            {{--                <span>Requested Posts</span>--}}
            {{--                <i class="ti tabler-chevron-right"></i>--}}
            {{--              </div>--}}
            {{--            </a>--}}

            <a href="{{ route('requests.index',  $society->uuid )}}"
               class="d-block text-decoration-none mt-3 text-dark">
              <div class="d-flex justify-content-between align-items-center">
                <span> Requested Posts </span>
                <i class="ti tabler-chevron-right"></i>
              </div>
            </a>

          @endcan
        </div>
      </div>
    </div>

    {{-- More Boards --}}
    <div class="col mb-xl-5">
      <div class="card border-0 shadow-sm h-100 position-sticky" style="top: 20px;">
        <div class="card-header bg-label-dark text-white py-2 mb-2">
          <h6 class="mb-0">More boards</h6>
        </div>
        <div class="card-body">
          @php $inSociety = isset($society) && isset($uuid); @endphp
            <a href="{{ $inSociety ? 'javascript:void(0)' : route('posts.index', 'discussions') }}"
               class="d-block text-decoration-none mb-3"
               @if ($inSociety) onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-discussions')).show()" @endif>
              <div class="mb-1 fw-semibold text-dark">Discussions</div>
              <div class="d-flex align-items-center gap-1 text-muted small">
                <i class="far fa-file-lines"></i>
                <span>{{ number_format($counts['discussionsCount']) }} Posts</span>
              </div>
            </a>

            <a href="{{ $inSociety ? 'javascript:void(0)' : route('posts.index', 'suggestions') }}"
               class="d-block text-decoration-none mb-3"
               @if ($inSociety) onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-suggestions')).show()" @endif>
              <div class="mb-1 fw-semibold text-dark">Suggestions</div>
              <div class="d-flex align-items-center gap-1 text-muted small">
                <i class="far fa-file-lines"></i>
                <span>{{ number_format($counts['suggestionsCount']) }} Posts</span>
              </div>
            </a>

            <a href="{{ $inSociety ? 'javascript:void(0)' : route('posts.index', 'issues') }}"
               class="d-block text-decoration-none"
               @if ($inSociety) onclick="bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-issues')).show()" @endif>
              <div class="mb-1 fw-semibold text-dark">Issues</div>
              <div class="d-flex align-items-center gap-1 text-muted small">
                <i class="far fa-file-lines"></i>
                <span>{{ number_format($counts['issuesCount']) }} Posts</span>
              </div>
            </a>
        </div>
      </div>
    </div>

    {{-- Community Rules --}}
    <div class="col mb-xl-0">
      <div class="card border-0 shadow-sm h-100 position-sticky" style="top: 20px;">
        <div class="card-header bg-label-dark text-white py-2">
          <h6 class="mb-0">Community Rules</h6>
        </div>
        <div class="card-body p-0">
          @forelse ($rules as $rule)
            @php
              $collapseId = 'ruleCollapse' . $loop->index;
            @endphp
            <div class="border-bottom">
              <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">{{ $rule->name }}</h6>
                <a class="card-collapsible" data-bs-toggle="collapse" href="#{{ $collapseId }}">
                  <i class="icon-base ti tabler-chevron-down"></i>
                </a>
              </div>
              <div id="{{ $collapseId }}" class="collapse">
                <div class="card-body">
                  <p class="mb-0 text-muted small">{{ $rule->description }}</p>
                </div>
              </div>
            </div>

          @empty
            <div class="text-center py-3 text-muted">
              No Rules Found
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
