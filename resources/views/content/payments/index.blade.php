@extends('layouts.layoutMaster')

@section('title', $title)

@section('page-style')
  @parent
  <style>
    /* ---------- Searchable select (vanilla JS replacement for Select2) ---------- */
    .input-group > .searchable-select {
      position: relative;
      flex: 1 1 auto;
      width: 1%;
      min-width: 0;
    }

    .searchable-select__input {
      width: 100%;
      border-start-start-radius: 0;
      border-end-start-radius: 0;
      border-inline-start: 0;
      cursor: pointer;
    }

    .searchable-select__menu {
      position: absolute;
      inset-inline: 0;
      top: 100%;
      z-index: 1056;
      max-height: 15rem;
      margin: 0.25rem 0 0;
      padding: 0.25rem;
      overflow-y: auto;
      list-style: none;
      background: var(--bs-body-bg, #fff);
      border: 1px solid var(--bs-border-color, #dbdade);
      border-radius: 0.375rem;
      box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.12);
    }

    .searchable-select__menu[hidden] {
      display: none;
    }

    .searchable-select__option,
    .searchable-select__empty {
      padding: 0.4rem 0.65rem;
      border-radius: 0.25rem;
    }

    .searchable-select__option {
      cursor: pointer;
    }

    .searchable-select__option.is-active,
    .searchable-select__option:hover {
      background: var(--bs-light, #f1f0f2);
    }

    .searchable-select__option.is-selected {
      font-weight: 600;
      color: var(--bs-primary, #7367f0);
    }

    .searchable-select__empty {
      color: var(--bs-secondary-color, #6c757d);
    }
  </style>
@endsection

@section('content')
  @php
    $isAllTransactionsPage = $slug === 'all_transactions';

    // Summary cards: [key, label, icon, color, default note]
    $summaryCards = [
      ['all',     'All Payments',     'tabler-cash',              'primary', 'transactions received'],
      ['overdue', 'Overdue Payments', 'tabler-clock-exclamation', 'warning', 'overdue charges'],
      ['refunds', 'Refunds',          'tabler-receipt-refund',    'danger',  'refunded payments'],
    ];

    // Status tabs: [key, label, icon]
    $statusTabs = [
      ['all',     'All Payments', 'tabler-cash'],
      ['paid',    'Paid',         'tabler-circle-check'],
      ['pending', 'Pending',      'tabler-clock-exclamation'],
      ['refunds', 'Refunds',      'tabler-receipt-refund'],
    ];

    $periodOptions = ['week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];
    $defaultPeriod = 'month';
  @endphp

  {{-- ================= Page header ================= --}}
  <div
    class="d-flex align-items-center justify-content-between gap-3 bg-light rounded-3 p-4 mb-4 overflow-hidden position-relative">
    <div class="d-flex align-items-center gap-3">
      <div class="avatar avatar-lg">
        <span class="avatar-initial rounded-2 bg-label-primary"><i class="ti tabler-receipt fs-2"></i></span>
      </div>
      <div>
        <p class="text-dark opacity-75 small text-uppercase fw-bold mb-1">Payments Management</p>
        <h4 class="mb-1">{{ $title }}</h4>
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('dashboard.analytics') }}">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
        </ol>
      </div>
    </div>

    {{-- NOTE: "breadcumb_section_pic" is spelled this way in the existing project stylesheet. --}}
    <i class="ti tabler-receipt text-dark opacity-25 position-absolute end-0 me-4 breadcumb_section_pic"
       aria-hidden="true"></i>
  </div>

  {{-- ================= Overview: period + refresh drive the cards and the table ================= --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
      <h5 class="mb-0">Overview</h5>
      <small class="text-muted">Cards and records follow the selected period</small>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3">
      <small class="text-muted" id="last-updated-label" aria-live="polite"></small>

      <ul class="nav nav-pills flex-row flex-nowrap bg-light rounded p-1 mb-0" id="period-switcher">
        @foreach($periodOptions as $periodKey => $periodLabel)
          <li class="nav-item">
            <button type="button"
                    class="nav-link py-1 {{ $periodKey === $defaultPeriod ? 'active' : '' }}"
                    data-period="{{ $periodKey }}">{{ $periodLabel }}</button>
          </li>
        @endforeach
      </ul>

      <button type="button" class="btn btn-outline-primary" id="refresh-button">
        <i class="ti tabler-refresh me-1"></i>Refresh
      </button>
    </div>
  </div>

  {{-- ================= Summary cards ================= --}}
  <div class="row g-3 mb-4">
    @foreach($summaryCards as [$cardKey, $cardLabel, $cardIcon, $cardColor, $cardNote])
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body d-flex align-items-start justify-content-between gap-3">
            <div>
              <div class="text-uppercase small text-muted fw-semibold mb-1">{{ $cardLabel }}</div>
              <h3 class="mb-0" id="card-{{ $cardKey }}-count">0</h3>
              <div class="fw-semibold mt-1">
                <span id="card-{{ $cardKey }}-total">0.00</span>
                <span class="text-muted small fw-normal">{{ $currency }}</span>
              </div>
              <small class="text-muted d-block" id="card-{{ $cardKey }}-note">{{ $cardNote }}</small>
            </div>
            <div class="avatar avatar-lg flex-shrink-0">
              <span class="avatar-initial rounded-2 bg-label-{{ $cardColor }}">
                <i class="ti {{ $cardIcon }} fs-2"></i>
              </span>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    {{-- ================= Status tabs ================= --}}
    <div class="card-header px-4 py-3">
      <ul class="nav nav-pills mb-0" id="status-tabs">
        @foreach($statusTabs as [$tabKey, $tabLabel, $tabIcon])
          <li class="nav-item">
            <a class="nav-link {{ $tabKey === 'all' ? 'active' : '' }}" href="#" data-status-tab="{{ $tabKey }}">
              <i class="ti {{ $tabIcon }} me-1"></i>{{ $tabLabel }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="card-body p-4">
      {{-- ================= Filters ================= --}}
      <div class="border rounded-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
          <div class="d-flex align-items-center gap-2">
            <i class="ti tabler-filter text-primary"></i>
            <span class="fw-semibold">Filters</span>
            <span class="badge bg-label-secondary" id="active-filter-badge">None</span>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="apply-filters-button">
              <i class="ti tabler-filter me-1"></i>Apply
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="reset-filters-button">
              <i class="ti tabler-refresh me-1"></i>Reset
            </button>
          </div>
        </div>

        <div class="alert alert-warning d-none rounded-0 border-0 border-bottom mb-0 py-2 px-3 small"
             id="filter-error-message" role="alert"></div>

        <div class="row g-3 p-3">
          @if($isAllTransactionsPage)
            <div class="col-sm-6 col-lg-3">
              <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-society">Society</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                  <i class="ti tabler-building-community text-muted"></i>
                </span>
                <select class="form-select border-start-0" id="filter-society" data-filter="society_id"
                        data-searchable>
                  <option value="">All societies</option>
                  @foreach($societies as $society)
                    <option value="{{ $society->id }}">{{ $society->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            @if($isSuperAdmin)
              <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-society-owner">
                  Society Owner
                </label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="ti tabler-user-circle text-muted"></i>
                  </span>
                  <select class="form-select border-start-0" id="filter-society-owner" data-filter="society_owner"
                          data-searchable>
                    <option value="">All society owners</option>
                    @foreach($societyOwners as $societyOwner)
                      <option value="{{ $societyOwner->id }}">{{ $societyOwner->full_name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            @endif
          @endif

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-property">Property</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-building text-muted"></i></span>
              <input type="search" class="form-control border-start-0" id="filter-property"
                     data-filter="property" placeholder="Property name or number">
            </div>
          </div>

          @if($isAllTransactionsPage)
            <div class="col-sm-6 col-lg-3">
              <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-property-owner">
                Property Owner
              </label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="ti tabler-user text-muted"></i></span>
                <input type="search" class="form-control border-start-0" id="filter-property-owner"
                       data-filter="owner" placeholder="Owner name">
              </div>
            </div>
          @endif

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-size-min">
              Property Size
            </label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="ti tabler-ruler-measure text-muted"></i>
              </span>
              <input type="number" min="0" step="any" class="form-control border-start-0" id="filter-size-min"
                     data-filter="size_min" placeholder="Min" aria-label="Minimum property size">
              <span class="input-group-text bg-light">–</span>
              <input type="number" min="0" step="any" class="form-control border-start-0" id="filter-size-max"
                     data-filter="size_max" placeholder="Max" aria-label="Maximum property size">
              <select class="form-select" id="filter-size-unit" data-filter="size_unit"
                      aria-label="Property size unit">
                <option value="marla">Marla</option>
                <option value="square_yard">Sq. Yard</option>
              </select>
            </div>
          </div>

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-search">Search</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-search text-muted"></i></span>
              <input type="search" class="form-control border-start-0" id="filter-search"
                     data-filter="q" placeholder="Reference or charge name">
            </div>
          </div>

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-method">Method</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="ti tabler-credit-card text-muted"></i>
              </span>
              <select class="form-select border-start-0" id="filter-method" data-filter="method">
                <option value="">All methods</option>
                @foreach($methods as $method)
                  <option value="{{ $method }}">{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-date-from">From</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-calendar text-muted"></i></span>
              <input type="text" class="form-control border-start-0" id="filter-date-from"
                     data-filter="from" data-datepicker placeholder="Select date" autocomplete="off">
            </div>
          </div>

          <div class="col-sm-6 col-lg-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" for="filter-date-to">To</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="ti tabler-calendar text-muted"></i></span>
              <input type="text" class="form-control border-start-0" id="filter-date-to"
                     data-filter="to" data-datepicker placeholder="Select date" autocomplete="off">
            </div>
          </div>
        </div>
      </div>

      {{-- ================= Records (current page) ================= --}}
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
          <tr class="text-nowrap" id="records-table-head"></tr>
          </thead>
          <tbody id="records-table-body"></tbody>
        </table>
      </div>

      <div class="mt-3" id="pagination-container">
        {!! $transactions->withQueryString()->links('pagination::bootstrap-5') !!}
      </div>
    </div>
  </div>

  @include('components.property.transaction-details-modal')
@endsection

@push('scripts')
  <script>
    (() => {
      'use strict';


      const RECORDS_URL = @json(route('payments.data', $slug));
      const IS_ALL_TRANSACTIONS_PAGE = @json($isAllTransactionsPage);

      const DEFAULT_PERIOD = @json($defaultPeriod);
      const DEFAULT_STATUS_TAB = 'all';
      const SUMMARY_CARD_KEYS = ['all', 'overdue', 'refunds'];

      const TYPING_DELAY_MS = 300;
      const LAST_UPDATED_REFRESH_MS = 30000;

      // =====================================================================
      // DOM references
      // =====================================================================
      const byId = id => document.getElementById(id);

      const periodSwitcher = byId('period-switcher');
      const statusTabs = byId('status-tabs');
      const refreshButton = byId('refresh-button');
      const applyFiltersButton = byId('apply-filters-button');
      const resetFiltersButton = byId('reset-filters-button');
      const activeFilterBadge = byId('active-filter-badge');
      const filterErrorMessage = byId('filter-error-message');
      const lastUpdatedLabel = byId('last-updated-label');
      const tableHead = byId('records-table-head');
      const tableBody = byId('records-table-body');
      const paginationContainer = byId('pagination-container');

      // Every filter control is tagged with data-filter="<request parameter name>".
      const filterInputs = [...document.querySelectorAll('[data-filter]')];
      const filterInputByName = Object.fromEntries(filterInputs.map(input => [input.dataset.filter, input]));

      // =====================================================================
      // State
      // =====================================================================
      const viewState = {
        statusTab: DEFAULT_STATUS_TAB,
        period: DEFAULT_PERIOD,
        page: 1
      };

      let loadedRows = [];
      let lastLoadedAt = null;
      let pendingRequest = null;      // AbortController of the request in flight
      let typingTimerId = null;
      let visibleColumnCount = 8;

      // =====================================================================
      // Small helpers
      // =====================================================================
      const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;'
      }[character]));

      const formatMoney = amount => Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const renderBadge = (text, color) =>
        `<span class="badge bg-label-${color} text-capitalize">${escapeHtml(text)}</span>`;

      const isTypedFilter = input => input.tagName === 'INPUT' && !('datepicker' in input.dataset);

      // =====================================================================
      // Filters: read / validate / reset
      // =====================================================================
      // Remember what each control looked like on load so "Reset" and the
      // "active filters" badge know what counts as the default.
      filterInputs.forEach(input => {
        input.dataset.defaultValue = input.value;
      });

      const readFilterValues = () => {
        const values = {};
        filterInputs.forEach(input => {
          const value = input.value.trim();
          if (value) values[input.dataset.filter] = value;
        });
        return values;
      };

      const countActiveFilters = () => filterInputs.filter(input =>
        input.value.trim() !== '' && input.value !== input.dataset.defaultValue
      ).length;

      const updateActiveFilterBadge = () => {
        const activeCount = countActiveFilters();
        activeFilterBadge.textContent = activeCount ? `${activeCount} active` : 'None';
        activeFilterBadge.className = `badge ${activeCount ? 'bg-label-primary' : 'bg-label-secondary'}`;
        resetFiltersButton.disabled = activeCount === 0 && viewState.period === DEFAULT_PERIOD;
      };

      /** Returns an error message when the filter combination makes no sense, otherwise null. */
      const validateFilters = () => {
        const { size_min, size_max, from, to } = Object.fromEntries(
          Object.entries(filterInputByName).map(([name, input]) => [name, input.value.trim()])
        );

        filterInputs.forEach(input => input.classList.remove('is-invalid'));

        if (size_min && size_max && Number(size_min) > Number(size_max)) {
          filterInputByName.size_min.classList.add('is-invalid');
          filterInputByName.size_max.classList.add('is-invalid');
          return 'Minimum property size cannot be greater than the maximum.';
        }

        if (from && to && from > to) {
          filterInputByName.from.classList.add('is-invalid');
          filterInputByName.to.classList.add('is-invalid');
          return 'The "From" date cannot be later than the "To" date.';
        }

        return null;
      };

      const showFilterError = message => {
        filterErrorMessage.textContent = message || '';
        filterErrorMessage.classList.toggle('d-none', !message);
      };

      // =====================================================================
      // Table column definitions: [header label, cell CSS classes, cell renderer]
      // =====================================================================
      const renderPropertyCell = row => {
        const propertyName = row.property_name || row.property || '—';
        const propertyNumber = row.property_no && row.property_no !== propertyName
          ? `<small class="text-muted d-block">${escapeHtml(row.property_no)}</small>`
          : '';

        return `<div class="fw-semibold">${escapeHtml(propertyName)}</div>${propertyNumber}`;
      };

      const renderReceiptActions = (row, rowIndex) => `
        <button type="button" class="btn btn-sm btn-icon btn-text-primary" data-view-row="${rowIndex}"
                title="View transaction details" aria-label="View transaction details">
          <i class="ti tabler-eye fs-5"></i>
        </button>
        <button type="button" class="btn btn-sm btn-icon btn-text-primary"
                onclick="printDocument('${escapeHtml(row.receipt_url)}')"
                title="Print the receipt" aria-label="Print the receipt">
          <i class="ti tabler-printer fs-5"></i>
        </button>
        <a href="${escapeHtml(row.download_url)}" class="btn btn-sm btn-icon btn-text-primary"
           title="Download the receipt (PDF)" aria-label="Download the receipt (PDF)">
          <i class="ti tabler-download fs-5"></i>
        </a>`;

      const getTransactionStatusColor = status => {
        if (status === 'returned') return 'danger';
        if (status === 'pending') return 'warning';
        return 'success';
      };

      const getChargeStatusColor = status => (/late|due/i.test(status || '') ? 'warning' : 'secondary');

      const societyAndOwnerColumns = IS_ALL_TRANSACTIONS_PAGE
        ? [
          ['Society', '', row => escapeHtml(row.society)],
          ['Owner', '', row => escapeHtml(row.owner)]
        ]
        : [];

      const COLUMNS_BY_RECORD_KIND = {
        transactions: [
          ['Date', 'text-nowrap', row => `<div class="fw-semibold">${escapeHtml(row.paid_at_label || '—')}</div>`],
          ['Property', '', renderPropertyCell],
          ['Society', '', row => escapeHtml(row.society || '—')],
          ['Charges', 'text-end text-nowrap', row => formatMoney(row.charge_subtotal)],
          ['Tax', 'text-end text-nowrap', row => formatMoney(row.tax_amount)
            + (Number(row.tax_rate_percent) > 0
              ? `<small class="text-muted d-block">${escapeHtml(row.tax_rate_percent)}%</small>` : '')],
          ['Late Fee', 'text-end text-nowrap', row => formatMoney(row.late_fee_amount)],
          ['Total', 'text-end text-nowrap fw-semibold', row => formatMoney(row.amount_paid)],
          ['Status', 'text-center text-nowrap', row => renderBadge(
            row.status_label || row.status || '-',
            getTransactionStatusColor(row.status)
          )],
          ['Receipt', 'text-center text-nowrap', renderReceiptActions]
        ],

        charges: [
          ['Charge', '', row => `<div class="fw-semibold">${escapeHtml(row.charge)}</div>`
            + (row.installment_no
              ? `<small class="text-muted d-block">Installment #${escapeHtml(row.installment_no)}</small>` : '')],
          ['Property', '', renderPropertyCell],
          ...societyAndOwnerColumns,
          ['Due Date', 'text-nowrap', row => escapeHtml(row.due_date || '—')],
          ['Amount Due', 'text-end text-nowrap', row => formatMoney(row.amount_due)],
          ['Amount Paid', 'text-end text-nowrap', row => formatMoney(row.amount_paid)],
          ['Remaining', 'text-end text-nowrap fw-semibold', row => formatMoney(row.remaining)],
          ['Status', 'text-nowrap', row => renderBadge(
            row.status,
            getChargeStatusColor(row.status_raw || row.status)
          )]
        ]
      };


      const showTableMessage = (html, cssClass = 'text-muted') => {
        tableBody.innerHTML =
          `<tr><td colspan="${visibleColumnCount}" class="text-center py-4 ${cssClass}">${html}</td></tr>`;
      };

      const showLoadingMessage = () =>
        showTableMessage('<span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading…');

      const showEmptyMessage = () => {
        const hasFilters = countActiveFilters() > 0;
        showTableMessage(
          `<div class="d-flex flex-column align-items-center justify-content-center">
       <i class="ti tabler-receipt-off fs-2 mb-2"></i>
       <span>No records found${hasFilters ? ' for the selected filters' : ' for this period'}.</span>
     </div>`
        );
      };

      const showErrorMessage = () => {
        showTableMessage(
          `Could not load records.
           <button type="button" class="btn btn-sm btn-outline-danger ms-2" data-retry>Try again</button>`,
          'text-danger'
        );
        paginationContainer.innerHTML = '';
      };

      const updateLastUpdatedLabel = () => {
        if (!lastLoadedAt) return;

        const minutesAgo = Math.floor((Date.now() - lastLoadedAt) / 60000);
        lastUpdatedLabel.textContent = minutesAgo < 1 ? 'Updated just now' : `Updated ${minutesAgo} min ago`;
      };

      const renderSummaryCards = cards => {
        SUMMARY_CARD_KEYS.forEach(cardKey => {
          const card = cards?.[cardKey];
          if (!card) return;

          byId(`card-${cardKey}-count`).textContent = card.count;
          byId(`card-${cardKey}-total`).textContent = formatMoney(card.total);
        });

        if (cards?.overdue) {
          byId('card-overdue-note').textContent = `charges across ${cards.overdue.plans} plan(s)`;
        }
      };

      const renderRecords = responseData => {
        const columns = COLUMNS_BY_RECORD_KIND[responseData.kind] || COLUMNS_BY_RECORD_KIND.transactions;
        visibleColumnCount = columns.length;
        loadedRows = responseData.rows || [];

        renderSummaryCards(responseData.cards);

        tableHead.innerHTML = columns
          .map(([label, cssClass]) => `<th class="${cssClass}">${label}</th>`)
          .join('');

        if (loadedRows.length === 0) {
          showEmptyMessage();
        } else {
          tableBody.innerHTML = loadedRows.map((row, rowIndex) => {
            const cells = columns
              .map(([, cssClass, renderCell]) => `<td class="${cssClass}">${renderCell(row, rowIndex)}</td>`)
              .join('');
            return `<tr>${cells}</tr>`;
          }).join('');
        }

        paginationContainer.innerHTML = responseData.pagination_html || '';

        lastLoadedAt = Date.now();
        updateLastUpdatedLabel();
        updateActiveFilterBadge();
      };

      // =====================================================================
      // URL sync: filters, tab, period and page survive a browser refresh
      // and can be shared as a link.
      // =====================================================================
      const buildRequestParams = () => new URLSearchParams({
        tab: viewState.statusTab,
        period: viewState.period,
        page: viewState.page,
        ...readFilterValues()
      });

      const saveStateToUrl = params => {
        const query = params.toString();
        history.replaceState(null, '', query ? `${location.pathname}?${query}` : location.pathname);
      };

      const restoreStateFromUrl = () => {
        const params = new URLSearchParams(location.search);

        if (params.has('tab')) {
          const tabLink = statusTabs.querySelector(`[data-status-tab="${CSS.escape(params.get('tab'))}"]`);
          if (tabLink) {
            viewState.statusTab = params.get('tab');
            setActiveNavItem(statusTabs, tabLink);
          }
        }

        if (params.has('period') && periodSwitcher.querySelector(`[data-period="${CSS.escape(params.get('period'))}"]`)) {
          setPeriod(params.get('period'));
        }

        viewState.page = parseInt(params.get('page'), 10) || 1;

        filterInputs.forEach(input => {
          if (params.has(input.dataset.filter)) input.value = params.get(input.dataset.filter);
        });
        searchableSelects.forEach(select => select.refreshDisplay());
      };

      // =====================================================================
      // Loading records
      // =====================================================================
      async function loadRecords() {
        const validationError = validateFilters();
        showFilterError(validationError);
        updateActiveFilterBadge();
        if (validationError) return;

        // Cancel the previous request so a slow, stale response can never overwrite a newer one.
        pendingRequest?.abort();
        pendingRequest = new AbortController();
        const currentRequest = pendingRequest;

        const params = buildRequestParams();
        saveStateToUrl(params);

        refreshButton.disabled = true;
        showLoadingMessage();

        try {
          const response = await fetch(`${RECORDS_URL}?${params}`, {
            signal: currentRequest.signal,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          if (!response.ok) throw new Error(`Request failed with status ${response.status}`);

          renderRecords(await response.json());
        } catch (error) {
          if (error.name === 'AbortError') return;   // superseded by a newer request
          console.error('Could not load payment records:', error);
          showErrorMessage();
        } finally {
          if (pendingRequest === currentRequest) {
            refreshButton.disabled = false;
            pendingRequest = null;
          }
        }
      }

      const reloadFromFirstPage = () => {
        clearTimeout(typingTimerId);
        viewState.page = 1;
        loadRecords();
      };

      const reloadAfterTyping = () => {
        clearTimeout(typingTimerId);
        typingTimerId = setTimeout(reloadFromFirstPage, TYPING_DELAY_MS);
      };

      // =====================================================================
      // Period switcher and status tabs
      // =====================================================================
      const setActiveNavItem = (navList, activeItem) => {
        navList.querySelectorAll('.nav-link').forEach(link => link.classList.toggle('active', link === activeItem));
      };

      const setPeriod = period => {
        viewState.period = period;
        setActiveNavItem(periodSwitcher, periodSwitcher.querySelector(`[data-period="${period}"]`));
      };

      periodSwitcher.addEventListener('click', event => {
        const periodButton = event.target.closest('[data-period]');
        if (!periodButton) return;

        setPeriod(periodButton.dataset.period);
        reloadFromFirstPage();
      });

      statusTabs.addEventListener('click', event => {
        const tabLink = event.target.closest('[data-status-tab]');
        if (!tabLink) return;

        event.preventDefault();
        setActiveNavItem(statusTabs, tabLink);
        viewState.statusTab = tabLink.dataset.statusTab;
        reloadFromFirstPage();
      });

      refreshButton.addEventListener('click', loadRecords);
      setInterval(updateLastUpdatedLabel, LAST_UPDATED_REFRESH_MS);

      // =====================================================================
      // Filter events
      // =====================================================================
      applyFiltersButton.addEventListener('click', reloadFromFirstPage);

      filterInputs.forEach(input => {
        if (isTypedFilter(input)) {
          // Text / number boxes: wait until the user stops typing; Enter searches right away.
          input.addEventListener('input', () => {
            updateActiveFilterBadge();
            reloadAfterTyping();
          });
          input.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
              event.preventDefault();
              reloadFromFirstPage();
            }
          });
        } else {
          // Dropdowns and date pickers: reload as soon as a value is chosen.
          input.addEventListener('change', () => {
            updateActiveFilterBadge();
            reloadFromFirstPage();
          });
        }
      });

      resetFiltersButton.addEventListener('click', () => {
        filterInputs.forEach(input => {
          input._flatpickr?.clear(false);
          input.value = input.dataset.defaultValue;
        });
        searchableSelects.forEach(select => select.refreshDisplay());

        setPeriod(DEFAULT_PERIOD);
        reloadFromFirstPage();
      });

      // =====================================================================
      // Table and pagination actions
      // =====================================================================
      tableBody.addEventListener('click', event => {
        if (event.target.closest('[data-retry]')) {
          loadRecords();
          return;
        }

        const viewButton = event.target.closest('[data-view-row]');
        if (viewButton) window.openTransactionDetails?.(loadedRows[Number(viewButton.dataset.viewRow)]);
      });

      // The server renders the page links; intercept them so only the table reloads.
      paginationContainer.addEventListener('click', event => {
        const pageLink = event.target.closest('a.page-link');
        if (!pageLink) return;

        event.preventDefault();
        viewState.page = parseInt(new URL(pageLink.href, location.origin).searchParams.get('page'), 10) || 1;
        loadRecords();
      });

      // =====================================================================
      // Searchable select (plain JavaScript, replaces Select2)
      // Keeps the native <select> as the source of truth and fires its normal
      // "change" event, so the rest of the page treats it like any other select.
      // =====================================================================
      const searchableSelects = [];

      const createSearchableSelect = nativeSelect => {
        const options = [...nativeSelect.options].map(option => ({ value: option.value, label: option.text }));

        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select';

        const textInput = document.createElement('input');
        textInput.type = 'text';
        textInput.className = 'form-control searchable-select__input';
        textInput.autocomplete = 'off';
        textInput.setAttribute('role', 'combobox');
        textInput.setAttribute('aria-expanded', 'false');
        textInput.setAttribute('aria-controls', `${nativeSelect.id}-options`);
        textInput.setAttribute('aria-label', nativeSelect.closest('.col-sm-6')?.querySelector('label')?.textContent.trim() || 'Select');

        const menu = document.createElement('ul');
        menu.className = 'searchable-select__menu';
        menu.id = `${nativeSelect.id}-options`;
        menu.setAttribute('role', 'listbox');
        menu.hidden = true;

        wrapper.append(textInput, menu);
        nativeSelect.classList.add('d-none');
        nativeSelect.after(wrapper);

        let visibleOptions = [];
        let activeIndex = -1;

        const selectedLabel = () => options.find(option => option.value === nativeSelect.value)?.label || '';

        const refreshDisplay = () => {
          textInput.value = selectedLabel();
        };

        const highlight = index => {
          activeIndex = index;
          [...menu.children].forEach((item, itemIndex) => {
            item.classList.toggle('is-active', itemIndex === index);
            if (itemIndex === index) item.scrollIntoView({ block: 'nearest' });
          });
        };

        const renderMenu = searchTerm => {
          const needle = searchTerm.trim().toLowerCase();
          visibleOptions = options.filter(option => option.label.toLowerCase().includes(needle));
          activeIndex = -1;

          menu.innerHTML = visibleOptions.length
            ? visibleOptions.map(option => `
                <li role="option" class="searchable-select__option ${option.value === nativeSelect.value ? 'is-selected' : ''}"
                    data-value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</li>`).join('')
            : '<li class="searchable-select__empty">No matches found</li>';
        };

        const openMenu = (searchTerm = '') => {
          renderMenu(searchTerm);
          menu.hidden = false;
          textInput.setAttribute('aria-expanded', 'true');
        };

        const closeMenu = () => {
          menu.hidden = true;
          textInput.setAttribute('aria-expanded', 'false');
          refreshDisplay();
        };

        const chooseValue = value => {
          const hasChanged = nativeSelect.value !== value;
          nativeSelect.value = value;
          closeMenu();
          if (hasChanged) nativeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        };

        textInput.addEventListener('focus', () => {
          textInput.select();
          openMenu();
        });
        textInput.addEventListener('click', () => {
          if (menu.hidden) openMenu();
        });
        textInput.addEventListener('input', () => openMenu(textInput.value));

        textInput.addEventListener('keydown', event => {
          if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (menu.hidden) openMenu(textInput.value);
            const step = event.key === 'ArrowDown' ? 1 : -1;
            highlight((activeIndex + step + visibleOptions.length) % (visibleOptions.length || 1));
          } else if (event.key === 'Enter' && !menu.hidden) {
            event.preventDefault();
            const pickedOption = visibleOptions[activeIndex] ?? (visibleOptions.length === 1 ? visibleOptions[0] : null);
            if (pickedOption) chooseValue(pickedOption.value);
          } else if (event.key === 'Escape' || event.key === 'Tab') {
            closeMenu();
          }
        });

        // mousedown (not click) so the choice lands before the input loses focus.
        menu.addEventListener('mousedown', event => {
          const optionItem = event.target.closest('[data-value]');
          if (!optionItem) return;

          event.preventDefault();
          chooseValue(optionItem.dataset.value);
        });

        document.addEventListener('mousedown', event => {
          if (!wrapper.contains(event.target) && !menu.hidden) closeMenu();
        });

        refreshDisplay();
        return { refreshDisplay };
      };

      // =====================================================================
      // Date pickers (flatpickr is loaded by the theme scripts, after this
      // inline script, so it is attached once the page is ready)
      // =====================================================================
      const initDatePickers = () => {
        if (!window.flatpickr) return;

        document.querySelectorAll('[data-datepicker]').forEach(input => {
          if (!input._flatpickr) {
            input.flatpickr({ monthSelectorType: 'static', dateFormat: 'Y-m-d', allowInput: true });
          }
        });
      };

      const initPage = () => {
        document.querySelectorAll('select[data-searchable]').forEach(select => {
          searchableSelects.push(createSearchableSelect(select));
        });

        initDatePickers();
        restoreStateFromUrl();
        loadRecords();
      };

      document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', initPage)
        : initPage();
    })();
  </script>
@endpush
