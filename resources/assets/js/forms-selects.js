/**
 * Selects & Tags
 */

'use strict';

$(function() {
  const selectPicker = $('.selectpicker'),
    select2Icons = $('.select2-icons');


  if (selectPicker.length) {
    selectPicker.selectpicker();
  }


  function initSelect2($el) {
    if ($el.hasClass('select2-hidden-accessible')) return; // avoid double init
    $el.wrap('<div class="position-relative"></div>').select2({
      placeholder: 'Select multiple',
      width: '100%',
      dropdownParent: $el.parent()
    });
  }


  $('select.select2').each(function() {
    const $this = $(this);
    if ($this.closest('.modal').length) return; // modal ones are deferred below
    initSelect2($this);
  });


  $(document).on('shown.bs.modal', '.modal', function() {
    $(this).find('select.select2').each(function() {
      initSelect2($(this));
    });
  });


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
