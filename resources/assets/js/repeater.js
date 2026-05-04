'use strict';

$(function () {
  // Select2
  // var select2 = $('.select2');
  // if (select2.length) {
  //   select2.each(function () {
  //     var $this = $(this);
  //     $this.wrap('<div class="position-relative"></div>').select2({
  //       dropdownParent: $this.parent(),
  //       placeholder: $this.data('placeholder') // for dynamic placeholder
  //     });
  //   });
  // }

  var formRepeater = $('.form-repeater');

  // Form Repeater
  // ! Using jQuery each loop to add dynamic id and class for inputs. You may need to improve it based on form fields.
  // -----------------------------------------------------------------------------------------------------------------


  if (formRepeater.length) {

    var row = 2;
    var col = 1;

    formRepeater.on('submit', function (e) {
      e.preventDefault();
    });
    var maxRows = 8;
    formRepeater.repeater({
      show: function () {
        let currentRows = $('[data-repeater-item]').length;
        console.log(currentRows)
        if (currentRows > maxRows) {
          const message = "You can add only eight dimensions!";
          notify(message, 'warning');
          $(this).remove();
          return;
        }

        var formControl = $(this).find('.form-control, .form-select');
        var formLabel = $(this).find('.form-label');

        formControl.each(function (i) {
          var id = 'form-repeater-' + currentRows + '-' + i;
          $(formControl[i]).attr('id', id);
          $(formLabel[i]).attr('for', id);
        });

        $(this).slideDown();
        // $('.select2').select2({
        //   dropdownParent: $(this).parent()
        // });
      }
    });
  }
});


