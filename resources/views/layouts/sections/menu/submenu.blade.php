@php
  use Illuminate\Support\Facades\Route;

  $currentRouteName = Route::currentRouteName();
  $currentSlug = request()->route('slug');
  $currentPath = request()->path();
@endphp

<ul class="menu-sub">
  @foreach ($menu as $submenu)
    @if (!canViewMenu($submenu))
      @continue
    @endif

    @php
      $activeClass = '';
      $activeParent = 'active open';

      if (
          isset($submenu->route, $submenu->slug) &&
          $currentRouteName === $submenu->route &&
          $currentSlug === $submenu->slug
      ) {
          $activeClass = 'active';
      }

      if (isset($submenu->url) && trim($submenu->url, '/') === $currentPath) {
          $activeClass = 'active';
      }

      // Child submenu
      if (isset($submenu->submenu)) {
          foreach ($submenu->submenu as $child) {
              if (
                  isset($child->route, $child->slug) &&
                  $currentRouteName === $child->route &&
                  $currentSlug === $child->slug
              ) {
                  $activeClass = $activeParent;
                  break;
              }

              if (isset($child->url) && trim($child->url, '/') === $currentPath) {
                  $activeClass = $activeParent;
                  break;
              }
          }
      }
    @endphp

    <li class="menu-item {{ $activeClass }}">
      <a href="{{ isset($submenu->route)
          ? route($submenu->route, ['slug' => $submenu->slug])
          : (isset($submenu->url)
              ? url($submenu->url)
              : 'javascript:void(0)') }}"
        class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}">

        @isset($submenu->icon)
          <i class="{{ $submenu->icon }}"></i>
        @endisset

        <div>{{ __($submenu->name) }}</div>

        @isset($submenu->badge)
          <div class="badge bg-{{ $submenu->badge[0] }} rounded-pill ms-auto">
            {{ $submenu->badge[1] }}
          </div>
        @endisset
      </a>

      {{-- Recursive --}}
      @isset($submenu->submenu)
        @include('layouts.sections.menu.submenu', ['menu' => $submenu->submenu])
      @endisset
    </li>
  @endforeach
</ul>
