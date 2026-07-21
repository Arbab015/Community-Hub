@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Landing - Front Pages')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/nouislider/nouislider.scss', 'resources/assets/vendor/libs/swiper/swiper.scss'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/front-page-landing.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/nouislider/nouislider.js', 'resources/assets/vendor/libs/swiper/swiper.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/property_search.js'])
@endsection

@section('content')
  <div data-bs-spy="scroll" class="scrollspy-example">

    <!-- Hero: Start -->
    <section id="hero-animation">
      <div id="landingHero" class="section-py landing-hero position-relative">
        <img src="{{ asset('assets/img/front-pages/backgrounds/hero-bg.png') }}"
             alt="hero background"
             class="position-absolute top-0 start-50 translate-middle-x object-fit-cover w-100 h-100"
             data-speed="1" />
        <div class="container">
          <div class="hero-text-box text-center position-relative">
            <h1 class="text-primary hero-title display-6 fw-extrabold">
              One Platform to Manage Your Entire Community
            </h1>
            <h2 class="hero-sub-title h6 mb-6">
              Simplify society operations, resident communication, billing, maintenance,<br class="d-none d-lg-block" />
              and security from a single dashboard.
            </h2>
          </div>

          <div style="position:relative;z-index:1;isolation:isolate;">
            @include('frontend.components.search_section')
          </div>
        </div>
      </div>
      <div class="landing-hero-blank d-none"></div>
    </section>
    <!-- Hero: End -->

  </div>
@endsection

