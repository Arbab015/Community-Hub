@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Landing - Front Pages')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/nouislider/nouislider.scss', 'resources/assets/vendor/libs/swiper/swiper.scss' ,'resources/assets/vendor/libs/notyf/notyf.scss',])
@endsection

<!-- Page Styles -->
@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/front-page-landing.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/nouislider/nouislider.js', 'resources/assets/vendor/libs/swiper/swiper.js' , 'resources/assets/vendor/libs/notyf/notyf.js',])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite(['resources/assets/js/front-page-landing.js', 'resources/assets/js/ui-toasts.js'])
@endsection


@section('content')
  <div data-bs-spy="scroll" class="scrollspy-example">

    <section id="landingContact" class="section-py bg-body landing-contact">
      <div class="container">
        <div class="text-center mb-4">
          <span class="badge bg-label-primary">Contact US</span>
        </div>
        <h4 class="text-center mb-1">
        <span class="position-relative fw-extrabold z-1">Let's work
          <img src="{{ asset('assets/img/front-pages/icons/section-title-icon.png') }}" alt="laptop charging"
               class="section-title-img position-absolute object-fit-contain bottom-0 z-n1" />
        </span>
          together
        </h4>
        <p class="text-center mb-12 pb-md-4">Any question or remark? just write us a message</p>
        <div class="row g-6">
          <div class="col-lg-5">
            <div class="contact-img-box position-relative border p-2 h-100">
              <img src="{{ asset('assets/img/front-pages/icons/contact-border.png') }}" alt="contact border"
                   class="contact-border-img position-absolute d-none d-lg-block scaleX-n1-rtl" />
              <img src="{{ asset('assets/img/front-pages/landing-page/contact-customer-service.png') }}"
                   alt="contact customer service" class="contact-img w-100 scaleX-n1-rtl" />
              <div class="p-4 pb-2">
                <div class="row g-4">
                  <div class="col-md-6 col-lg-12 col-xl-6">
                    <div class="d-flex align-items-center">
                      <div class="badge bg-label-primary rounded p-1_5 me-3"><i
                          class="icon-base ti tabler-mail icon-lg"></i></div>
                      <div>
                        <p class="mb-0">Email</p>
                        <h6 class="mb-0"><a href="mailto:example@gmail.com" class="text-heading">example@gmail.com</a>
                        </h6>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6 col-lg-12 col-xl-6">
                    <div class="d-flex align-items-center">
                      <div class="badge bg-label-success rounded p-1_5 me-3"><i
                          class="icon-base ti tabler-phone-call icon-lg"></i></div>
                      <div>
                        <p class="mb-0">Phone</p>
                        <h6 class="mb-0"><a href="tel:+1234-568-963" class="text-heading">+1234 568 963</a></h6>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="card h-100">
              <div class="card-body">
                <h4 class="mb-2">Send a message</h4>
                <p class="mb-6">
                  If you would like to discuss anything related to payment, account, licensing,<br
                    class="d-none d-lg-block" />
                  partnerships, or have pre-sales questions, you’re at the right place.
                </p>
                <form id="contactForm">
                  @csrf
                  <div class="row g-4">
                    <div class="col-md-6">
                      <label class="form-label" for="contact-form-fullname">Full Name</label>
                      <input type="text" name="full_name" class="form-control" id="contact-form-fullname"
                             placeholder="john" required />
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="contact-form-email">Email</label>
                      <input type="email" name="email" id="contact-form-email" class="form-control"
                             placeholder="johndoe@gmail.com" required />
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="contact-form-message">Message</label>
                      <textarea name="message" id="contact-form-message" class="form-control" rows="7"
                                placeholder="Write a message" required></textarea>
                    </div>
                    <div class="col-12">
                      <button type="submit" class="btn btn-primary" id="contactSubmitBtn">Send inquiry</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Contact Us: End -->
  </div>

@endsection

@push('scripts')
  <script>
    document.getElementById('contactForm')?.addEventListener('submit', function(e) {
      e.preventDefault();

      const btn = document.getElementById('contactSubmitBtn');
      const form = e.target;
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Sending...';

      fetch("{{ route('contact.send') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          full_name: form.full_name.value,
          email: form.email.value,
          message: form.message.value
        })
      })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
          if (ok && data.success) {
            notify(data.message, 'success');
            form.reset();
          } else {
            notify(data.message ?? 'Failed to send message.', 'error');
          }
        })
        .catch(() => {
          if (window.notyf) window.notyf.open({ type: 'error', message: data.message ?? 'Failed to send message.' });
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = originalText;
        });
    });
  </script>
@endpush
