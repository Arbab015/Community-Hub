@php
  use Illuminate\Support\Facades\Route;

  $configData = Helper::appClasses();
  $currentRouteName = Route::currentRouteName();
  $currentSlug = request()->route('slug');
  $currentPath = request()->path();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu"
  @foreach ($configData['menuAttributes'] as $attribute => $value)
    {{ $attribute }}="{{ $value }}" @endforeach>

  {{-- App Brand --}}
  <div class="app-brand demo">
    <a href="{{ url('/') }}" class="app-brand-link">
      <span class="app-brand-logo demo">
        @include('_partials.macros')
      </span>
      <span class="app-brand-text demo menu-text fw-bold ms-3">
        {{ config('variables.templateName') }}
      </span>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  {{-- Menu --}}
  <ul class="menu-inner py-1">
    @foreach ($menuData[0]->menu as $menu)
      {{-- 🔐 Permission Check --}}
      @if (!canViewMenu($menu))
        @continue
      @endif

      @php
        $activeClass = '';
        $activeParent = $configData['layout'] === 'vertical' ? 'active open' : 'active';

        // Route slug array
        if (isset($menu->slug) && is_array($menu->slug) && in_array($currentRouteName, $menu->slug)) {
            $activeClass = $activeParent;
        }

        // Route slug string
        if (isset($menu->slug) && is_string($menu->slug) && $currentRouteName === $menu->slug) {
            $activeClass = $activeParent;
        }

        // URL match
        if (isset($menu->url) && trim($menu->url, '/') === $currentPath) {
            $activeClass = $activeParent;
        }

        // Submenu active check
        if (isset($menu->submenu)) {
            foreach ($menu->submenu as $sub) {
                if (
                    isset($sub->route, $sub->slug) &&
                    $currentRouteName === $sub->route &&
                    $currentSlug === $sub->slug
                ) {
                    $activeClass = $activeParent;
                    break;
                }

                if (isset($sub->url) && trim($sub->url, '/') === $currentPath) {
                    $activeClass = $activeParent;
                    break;
                }
            }
        }
      @endphp

      <li class="menu-item {{ $activeClass }}">
        <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0)' }}"
          class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}">

          @isset($menu->icon)
            <i class="{{ $menu->icon }}"></i>
          @endisset

          <div>{{ __($menu->name) }}</div>
        </a>

        {{-- Submenu --}}
        @isset($menu->submenu)
          @include('layouts.sections.menu.submenu', ['menu' => $menu->submenu])
        @endisset
      </li>
    @endforeach
  </ul>
</aside>
