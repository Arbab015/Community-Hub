<div class="modal fade" id="propertySearchModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
          <span class="d-inline-block bg-primary rounded hr_bar"></span>
          Search Properties
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body pt-3">
        <div class="input-group mb-6">
          <span class="input-group-text bg-transparent"><i class="ti tabler-search"></i></span>
          <input type="text" id="propertySearchTerm" class="form-control form-control-lg"
                 placeholder="Search by property name, property no or owner name" autocomplete="off">
          <button type="button" class="btn btn-outline-secondary d-none" id="propertySearchClear">
            <i class="ti tabler-x"></i>
          </button>
        </div>

        <div id="propertySearchResults" class="property-search-results mb-5">
          <div class="d-flex flex-column align-items-center justify-content-center text-center text-muted py-4"
               id="propertySearchHint">
            <i class="ti tabler-search fs-1 mb-4 opacity-50"></i>
            <span>Type at least two characters to search for a property.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('propertySearchModal');
        if (!modal) return;

        const input = document.getElementById('propertySearchTerm');
        const results = document.getElementById('propertySearchResults');
        const clearBtn = document.getElementById('propertySearchClear');
        const endpoint = @json(route('property.quick_search'));

        let timer = null;
        let runningTerm = null;

        const hintHtml = `
        <div class="text-center text-muted py-4">
          <i class="ti tabler-search fs-1 d-block mb-2 opacity-50"></i>
          <span class="d-block">Type at least two characters to search for a property.</span>
        </div>`;

        const render = (html) => {
          results.innerHTML = html;
        };

        const renderMessage = (icon, message) => `
        <div class="text-center text-muted py-4">
          <i class="ti ${icon} fs-1 d-block mb-2 opacity-50"></i>
          <span class="d-block">${message}</span>
        </div>`;

        // The results are built as DOM nodes - the property data is never
        // injected as HTML.
        const buildResult = (property) => {
          const link = document.createElement('a');
          link.className = 'list-group-item list-group-item-action d-flex align-items-center gap-3 py-3';
          link.href = property.url;

          const icon = document.createElement('span');
          icon.className = 'avatar avatar-sm rounded-circle bg-label-primary d-flex align-items-center justify-content-center flex-shrink-0';
          icon.innerHTML = '<i class="ti tabler-building-estate"></i>';
          link.appendChild(icon);

          const body = document.createElement('span');
          body.className = 'flex-grow-1 min-w-0';

          const title = document.createElement('span');
          title.className = 'd-block fw-semibold text-truncate';
          title.textContent = property.name || property.type || 'Property';
          body.appendChild(title);

          const meta = document.createElement('span');
          meta.className = 'd-block small text-muted text-truncate';
          const parts = [];
          if (property.owner_name) parts.push('Owner: ' + property.owner_name);
          if (property.location) parts.push(property.location);
          meta.textContent = parts.join(' · ') || 'No owner yet';
          body.appendChild(meta);

          link.appendChild(body);

          if (property.property_no) {
            const no = document.createElement('span');
            no.className = 'badge bg-label-secondary flex-shrink-0';
            no.textContent = '#' + property.property_no;
            link.appendChild(no);
          }

          const chevron = document.createElement('i');
          chevron.className = 'ti tabler-chevron-right text-muted flex-shrink-0';
          link.appendChild(chevron);

          return link;
        };

        const renderResults = (list, term) => {
          results.innerHTML = '';

          if (!list.length) {
            render(renderMessage('tabler-building-estate', 'No property matches "' + term + '".'));
            return;
          }

          const wrapper = document.createElement('div');
          wrapper.className = 'list-group list-group-flush';

          list.forEach((property) => wrapper.appendChild(buildResult(property)));

          results.appendChild(wrapper);

          const count = document.createElement('div');
          count.className = 'small text-muted text-center mt-2';
          count.textContent = list.length + (list.length === 1 ? ' property found' : ' properties found');
          results.appendChild(count);
        };

        const search = (term) => {
          if (term.length < 2) {
            runningTerm = null;
            render(hintHtml);
            return;
          }

          runningTerm = term;

          render(renderMessage('tabler-loader-2', 'Searching…'));

          fetch(endpoint + '?term=' + encodeURIComponent(term), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
          })
            .then((res) => res.json())
            .then((data) => {
              if (runningTerm !== term) return;
              renderResults(data.results || [], term);
            })
            .catch(() => {
              if (runningTerm !== term) return;
              render(renderMessage('tabler-alert-triangle', 'The search failed. Please try again.'));
            });
        };

        input.addEventListener('input', function() {
          const term = this.value.trim();
          clearBtn.classList.toggle('d-none', term.length === 0);
          clearTimeout(timer);
          timer = setTimeout(() => search(term), 300);
        });

        input.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(timer);
            search(this.value.trim());
          }
        });

        clearBtn.addEventListener('click', function() {
          input.value = '';
          this.classList.add('d-none');
          clearTimeout(timer);
          render(hintHtml);
          input.focus();
        });

        modal.addEventListener('shown.bs.modal', function() {
          input.focus();
        });
      });
    </script>
@endpush
