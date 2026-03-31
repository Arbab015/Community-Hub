@php
  $posts = match ($type) {
      'discussions' => $discussions,
      'suggestions' => $suggestions,
      'issues' => $issues,
  };
   // Filter rules for current type
 $filteredRules = $rules->filter(function($rule) use ($type) {
    return in_array($type, $rule->related_to ?? []);
});
@endphp
<div class="row g-5">
  <div class="col-md-9 col-12">
    @include('components.forum.index')
    <div class="mt-3">
      {!! $posts->withQueryString()->links('pagination::bootstrap-5') !!}
    </div>
  </div>

  {{-- Right section desktop only --}}
  <div class="d-none d-md-block col-md-3">
    @include('components.forum.right_section', ['rules' => $filteredRules])
  </div>
  <div class="offcanvas offcanvas-end" tabindex="-1" id="forumRightOffcanvas"
       aria-labelledby="forumRightOffcanvasLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="forumRightOffcanvasLabel"></h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      @include('components.forum.right_section', ['rules' => $filteredRules])
    </div>
  </div>
  @include('_partials._modals.report_an_issue')
</div>
