<!-- BEGIN: Vendor JS-->

@vite(['resources/assets/vendor/libs/jquery/jquery.js', 'resources/assets/vendor/libs/popper/popper.js', 'resources/assets/vendor/js/bootstrap.js', 'resources/assets/vendor/libs/node-waves/node-waves.js'])

@if ($configData['hasCustomizer'])
  @vite('resources/assets/vendor/libs/pickr/pickr.js')
@endif

@vite(['resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js', 'resources/assets/vendor/libs/hammer/hammer.js', 'resources/assets/vendor/js/menu.js'])

@yield('vendor-script')
<!-- END: Page Vendor JS-->

<!-- BEGIN: Theme JS-->
@vite(['resources/assets/js/main.js'])
<!-- END: Theme JS-->

<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->

<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->

<!-- app JS -->
@vite(['resources/js/app.js'])
<!-- END: app JS-->

<!-- Print documents in a hidden frame, so printing never opens a new tab. -->
<iframe id="pagePrintFrame" title="Print document" aria-hidden="true" tabindex="-1"
        style="position: fixed; left: -10000px; top: 0; width: 1024px; height: 768px; border: 0; margin: 0; padding: 0; opacity: 0; pointer-events: none; z-index: -1;"></iframe>

<script>
  (function () {
    var printSeq = 0;

    window.printDocument = function (url) {
      var frame = document.getElementById('pagePrintFrame');
      if (!frame || !url) return;

      var printed = false;
      var onLoad = function () {
        if (printed) return;
        printed = true;
        frame.removeEventListener('load', onLoad);
        try {
          frame.contentWindow.focus();
          frame.contentWindow.print();
        } catch (error) {
          window.open(url, '_blank');
        }
      };

      frame.addEventListener('load', onLoad);
      // The cache-buster keeps the load event firing when the same
      // document is printed twice in a row.
      frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + '_print=' + (++printSeq);
    };
  })();

  // A POST action (for example cancelling a reservation) can flash a document
  // to print once the next page has loaded. It prints through the same hidden
  // frame as every other receipt, so the user stays on the page they returned to.
  @if(session('print_document_url'))
    window.addEventListener('load', function () {
      if (typeof window.printDocument === 'function') {
        window.printDocument(@json(session('print_document_url')));
      }
    });
  @endif
</script>
