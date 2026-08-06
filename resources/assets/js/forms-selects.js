/**
 * Selects & Tags
 */

'use strict';

$(function() {
  const selectPicker = $('.selectpicker'),
    select2Icons = $('.select2-icons');

  // Bootstrap Select
  // --------------------------------------------------------------------
  if (selectPicker.length) {
    selectPicker.selectpicker();
  }

  // Select2
  // --------------------------------------------------------------------
  function initSelect2($el) {
    if ($el.hasClass('select2-hidden-accessible')) return; // avoid double init
    $el.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select multiple',
      width: '100%',
      dropdownParent: $el.parent()
    });
  }

  // Init any .select2 that's already visible on page load
  $('.select2').each(function() {
    const $this = $(this);
    if ($this.closest('.modal').length) return; // modal ones are deferred below
    initSelect2($this);
  });

  // Select2 inside a modal must be initialized only after the modal is
  // shown — Bootstrap modals are display:none until then, and Select2
  // can't measure width on a hidden element (causing collapsed boxes /
  // missing placeholder text).
  $(document).on('shown.bs.modal', '.modal', function() {
    $(this).find('.select2').each(function() {
      initSelect2($(this));
    });
  });

  // Select2 Icons
  // --------------------------------------------------------------------
  if (select2Icons.length) {
    function renderIcons(option) {
      if (!option.id) {
        return option.text;
      }
      var $icon = '<i class=\'' + $(option.element).data('icon') + ' me-2\'></i>' + option.text;
      return $icon;
    }

    select2Icons.wrap('<div class="position-relative"></div>').select2({
      dropdownParent: select2Icons.parent(),
      templateResult: renderIcons,
      templateSelection: renderIcons,
      escapeMarkup: function(es) {
        return es;
      }
    });
  }
});
