(function() {
  'use strict';

  const FREQUENCY_LABELS = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    biannually: 'Biannually',
    annually: 'Annually'
  };

  const C = window.InstallmentPlanCalculator;

  function escapeHtml(value) {
    const el = document.createElement('div');
    el.textContent = value;
    return el.innerHTML;
  }

  function formatNumber(value) {
    const n = parseFloat(value);
    return Number.isNaN(n) ? '' : Number(n).toLocaleString(undefined, { maximumFractionDigits: 2 });
  }

  function optionPayload(opt) {
    console.log(opt);
    return {
      name: opt.dataset.name || opt.text || 'Installment Plan',
      num: parseInt(opt.dataset.numberOfInstallments, 10) || 0,
      frequency: opt.dataset.frequency || '',
      addRaw: toFloat(opt.dataset.additional),
      addInPer: opt.dataset.additionalInPer === '1',
      balloonRaw: opt.dataset.balloon === undefined || opt.dataset.balloon === '' ? 0 : toFloat(opt.dataset.balloon),
      balloonInPer: opt.dataset.balloonInPer === '1',
      balloonFrequency: opt.dataset.balloonFrequency || null
    };
  }

  function toFloat(value) {
    const n = parseFloat(value);
    return Number.isNaN(n) ? 0 : n;
  }

  function renderPreview(select) {
    const container = document.getElementById(select.dataset.preview);
    const list = container && container.querySelector('.it-preview-list');
    if (!list) return;

    list.innerHTML = '';

    const priceBase = toFloat(container.dataset.priceBase);
    const includedTotal = toFloat(container.dataset.includedTotal);
    const excludedTotal = toFloat(container.dataset.excludedTotal);

    Array.from(select.selectedOptions)
      .filter(opt => opt.value)
      .forEach(opt => {
        const p = optionPayload(opt);
        console.log(p);
        const parts = ['<span class="fw-semibold">' + escapeHtml(p.name) + '</span>'];

        if (p.num > 0) {
          parts.push('<span class="badge bg-primary">' + p.num + ' installments</span>');
        }

        const freq = FREQUENCY_LABELS[p.frequency];
        if (freq) parts.push('<span>&middot; ' + freq + '</span>');

        if (p.addInPer || p.addRaw > 0) {
          parts.push('<span>&middot; Additional on installment ' + formatNumber(p.addRaw) + (p.addInPer ? '%' : '') + '</span>');
        }

        const hasPrice = priceBase > 0;

        if (!C) {
          parts.push('<span class="text-danger">&middot; calculator not loaded</span>');
        } else {
          const calc = C.calculate({
            listing_price: priceBase,
            included_charges_total: includedTotal,
            excluded_charges_total: excludedTotal,
            additional_amount: p.addInPer ? priceBase * p.addRaw / 100 : p.addRaw,
            number_of_installments: p.num,
            installment_frequency: p.frequency,
            balloon_payment: p.balloonRaw,
            balloon_payment_in_per: p.balloonInPer,
            balloon_payment_frequency: p.balloonFrequency
          });

          if (!calc.valid && calc.errors.length) {
            parts.push('<span class="text-danger">&middot; ' + escapeHtml(calc.errors[0]) + '</span>');
          } else if (calc.balloon_count > 0) {
            const balloonFreq = FREQUENCY_LABELS[p.balloonFrequency];
            parts.push('<span>&middot; ' + calc.balloon_count + ' balloons</span>');
            if (balloonFreq) parts.push('<span>(' + balloonFreq + ')</span>');
            if (hasPrice) {
              parts.push('<span>&middot; Per balloon: ' + formatNumber(calc.per_balloon) + '</span>');
            }
            if (hasPrice) {
              parts.push('<span>&middot; Per installment: ' + formatNumber(calc.per_installment) + ' (' + calc.normal_count + ' installments)</span>');
            }
          } else if (hasPrice) {
            parts.push('<span>&middot; Per installment: ' + formatNumber(calc.per_installment) + '</span>');
          }
        }

        const row = document.createElement('div');
        row.className = 'alert alert-primary py-2 px-3 mb-2 d-flex flex-wrap align-items-center gap-2';
        row.innerHTML = parts.join('');
        list.appendChild(row);
      });

    container.classList.toggle('d-none', list.children.length === 0);
  }

  // Live update on selection — Select2 events plus a native change fallback.
  if (window.jQuery) {
    $(document).on('select2:select select2:unselect', '.installment-template-select', function() {
      renderPreview(this);
    });
  }

  document.addEventListener('change', function(e) {
    if (e.target.matches && e.target.matches('.installment-template-select')) {

      renderPreview(e.target);
    }
  });

  // Re-render after a modal opens (Select2 restores the selections).
  document.addEventListener('shown.bs.modal', function(e) {
    e.target.querySelectorAll('.installment-template-select').forEach(renderPreview);
  });
})();
