{{-- Activates a tab pane from the query string, e.g. ?tab=payments. --}}
<script>
  (function () {
    var wanted = new URLSearchParams(window.location.search).get('tab');
    if (!wanted) return;

    var pane = document.getElementById(wanted);
    if (!pane) return;

    var trigger = document.querySelector('[data-bs-target="#' + wanted + '"]');
    if (trigger && window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
  })();
</script>
