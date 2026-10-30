<div class="modal fade" id="transactionDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">
          <i class="ti tabler-receipt-2 me-1 text-primary"></i> Transaction Details
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="transactionDetailsBody"></div>
      <div class="modal-footer">
        <div class="me-auto d-flex gap-2">
          <a href="#" id="transactionDetailsDownloadBtn"
             class="btn btn-sm btn-outline-primary d-none">
            <i class="ti tabler-download me-1"></i> Save
          </a>
          <button type="button" id="transactionDetailsPrintBtn"
                  class="btn btn-sm btn-primary d-none">
            <i class="ti tabler-printer me-1"></i> Print
          </button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  (() => {
    const esc = v => v === null || v === undefined ? '' : String(v).replace(/[&<>"']/g, c => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      '\'': '&#39;'
    }[c]));

    const money = n => Number(n || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });

    const value = v => v === null || v === undefined || v === '' || v === '—' ? null : v;

    const field = (label, v) => {
      const shown = value(v);
      if (shown === null) return '';

      return `<div class="col-sm-6">
        <div class="text-muted small text-uppercase fw-semibold mb-1">${esc(label)}</div>
        <div class="fw-semibold text-break">${esc(shown)}</div>
      </div>`;
    };

    window.openTransactionDetails = (row) => {
      if (!row) return;

      const body = document.getElementById('transactionDetailsBody');
      const modal = document.getElementById('transactionDetailsModal');
      const printBtn = document.getElementById('transactionDetailsPrintBtn');
      const downloadBtn = document.getElementById('transactionDetailsDownloadBtn');
      if (!body || !modal) return;

      const receiptUrl = row.receipt_url || null;
      const downloadUrl = row.download_url || null;

      if (printBtn) {
        printBtn.onclick = receiptUrl
          ? () => {
            if (typeof window.printDocument === 'function') {
              window.printDocument(receiptUrl);
            } else {
              window.open(receiptUrl, '_blank');
            }
          }
          : null;
        printBtn.classList.toggle('d-none', !receiptUrl);
      }

      if (downloadBtn) {
        if (downloadUrl) {
          downloadBtn.href = downloadUrl;
        } else {
          downloadBtn.removeAttribute('href');
        }
        downloadBtn.classList.toggle('d-none', !downloadUrl);
      }

      const currency = row.currency || window.PAYMENT_CURRENCY || 'PKR';
      const status = row.status || 'paid';
      const statusLabel = row.status_label || status.replace(/_/g, ' ');
      const badgeClass = status === 'returned' ? 'danger' : (status === 'pending' ? 'warning' : 'success');
      const paidAt = row.paid_at_full || row.paid_at_label || row.paid_at || null;
      const charges = Array.isArray(row.charges) ? row.charges : [];
      const taxRate = Number(row.tax_rate_percent) || 0;
      const owner = row.owner || row.owner_name;
      const property = (row.property_name && row.property_name !== '—') ? row.property_name : row.property;


      const chargesHtml = charges.length
        ? `<div class="d-flex flex-wrap gap-2">
             ${charges.map(name => `<span class="badge bg-label-secondary fw-normal">
               <i class="ti tabler-circle-check me-1"></i>${esc(name)}
             </span>`).join('')}
           </div>`
        : '<div class="text-muted">No charge recorded</div>';

      const returned = status === 'returned'
        ? `<div class="alert alert-danger d-flex gap-2 mb-4">
             <i class="ti tabler-alert-circle fs-4"></i>
             <div>
               <div class="fw-semibold">Payment returned</div>
               ${value(row.reversal_reason) ? `<div class="small">${esc(row.reversal_reason)}</div>` : ''}
               ${value(row.reversed_by) ? `<div class="small text-muted">Reversed by ${esc(row.reversed_by)}${value(row.reversed_at) ? ' on ' + esc(row.reversed_at) : ''}</div>` : ''}
             </div>
           </div>`
        : '';

      const propertyCard = `<div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <h6 class="text-uppercase text-muted small fw-semibold mb-3">
            <i class="ti tabler-building-community me-1"></i> Property
          </h6>
          <div class="row g-3">
            ${field('Property', property)}
            ${field('Property No', row.property_no)}
            ${field('Society', row.society)}
            ${field('Owner', owner)}
            ${field('Ownership Period', row.ownership_period)}
          </div>
        </div>
      </div>`;

      const paymentCard = `<div class="col-lg-6">
        <div class="border rounded-3 p-3 h-100">
          <h6 class="text-uppercase text-muted small fw-semibold mb-3">
            <i class="ti tabler-credit-card me-1"></i> Payment
          </h6>
          <div class="row g-3">
            ${field('Payment Method', row.method_label)}
            ${field('Reference No', row.reference_no)}
            ${field('Recorded By', row.recorded_by)}
            ${field('Recorded On', paidAt)}
          </div>
        </div>
      </div>`;

      body.innerHTML = `
        <div class="rounded-3 p-3 mb-4 bg-label-primary d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <div class="text-uppercase small fw-semibold text-primary mb-1">Total Paid</div>
            <h3 class="mb-1 text-primary">${esc(currency)} ${money(row.amount_paid)}</h3>
            ${paidAt ? `<div class="text-muted small"><i class="ti tabler-calendar me-1"></i>${esc(paidAt)}</div>` : ''}
          </div>
          <span class="badge bg-label-${badgeClass} text-capitalize px-3 py-2">${esc(statusLabel)}</span>
        </div>

        ${returned}

        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="text-uppercase text-muted small fw-semibold mb-0">Charges Settled</h6>
          <span class="badge bg-label-info">${charges.length}</span>
        </div>
        ${chargesHtml}

        <div class="row g-3 my-4">
          ${propertyCard}
          ${paymentCard}
        </div>

        ${value(row.description) ? `<div class="border rounded-3 p-3 mb-3">
          <div class="text-muted small text-uppercase fw-semibold mb-1">Description</div>
          <div class="text-break">${esc(row.description)}</div>
        </div>` : ''}

        <div class="border rounded-3 p-3">
          <div class="d-flex justify-content-between gap-2">
            <span class="text-muted">Charges Subtotal</span>
            <span class="fw-semibold">${esc(currency)} ${money(row.charge_subtotal)}</span>
          </div>
          <div class="d-flex justify-content-between gap-2 mt-2">
            <span class="text-muted">${taxRate > 0 ? 'FBR Tax (' + esc(taxRate) + '%)' : 'Tax'}</span>
            <span class="fw-semibold">${esc(currency)} ${money(row.tax_amount)}</span>
          </div>
          ${Number(row.late_fee_amount) > 0 ? `
          <div class="d-flex justify-content-between gap-2 mt-2">
            <span class="text-muted">Late Fee</span>
            <span class="fw-semibold text-warning">${esc(currency)} ${money(row.late_fee_amount)}</span>
          </div>` : ''}
          <hr class="my-2">
          <div class="d-flex justify-content-between gap-2">
            <span class="fw-semibold">Total Paid</span>
            <span class="fw-semibold text-success">${esc(currency)} ${money(row.amount_paid)}</span>
          </div>
        </div>
      `;

      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
      } else if (window.jQuery) {
        jQuery(modal).modal('show');
      }
    };
  })();
</script>
