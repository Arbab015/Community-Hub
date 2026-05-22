'use strict';

(function() {
  const flatpickrDate = document.querySelector('#dob');

  if (flatpickrDate) {
    flatpickrDate.flatpickr({
      monthSelectorType: 'static',
      dateFormat: 'Y-m-d',
      maxDate: 'today' // optional: prevent future dates for DOB
    });
  }
})();
