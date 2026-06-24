'use strict';

$(function() {

  var formRepeater = $('.form-repeater');

  if (formRepeater.length) {
    var row = 2;
    var col = 1;

    formRepeater.on('submit', function(e) {
      e.preventDefault();
    });
    var maxRows = 8;
    formRepeater.repeater({
      show: function() {
        let currentRows = $('[data-repeater-item]').length;
        console.log(currentRows);
        if (currentRows > maxRows) {
          const message = 'You can add only eight dimensions!';
          notify(message, 'warning');
          $(this).remove();
          return;
        }

        var formControl = $(this).find('.form-control, .form-select');
        var formLabel = $(this).find('.form-label');

        formControl.each(function(i) {
          var id = 'form-repeater-' + currentRows + '-' + i;
          $(formControl[i]).attr('id', id);
          $(formLabel[i]).attr('for', id);
        });

        $(this).slideDown();

      }
    });
  }
});

