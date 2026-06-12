/**
 * UI Toasts
 */

'use strict';

document.addEventListener('DOMContentLoaded', function(e) {
  // Custom Notyf class to allow HTML content in messages
  class CustomNotyf extends Notyf {
    _renderNotification(options) {
      const notification = super._renderNotification(options);

      // Replace textContent with innerHTML to render HTML content
      if (options.message) {
        notification.message.innerHTML = options.message;
      }

      return notification;
    }
  }

  // Initialize CustomNotyf instance with default behaviors
  window.notyf = new CustomNotyf({
    duration: 3000,
    ripple: true,
    dismissible: false,
    position: { x: 'right', y: 'top' },
    types: [
      {
        type: 'info',
        background: config.colors.info,
        className: 'notyf__info',
        icon: {
          className: 'icon-base ti tabler-info-circle-filled icon-md text-white',
          tagName: 'i'
        }
      },
      {
        type: 'warning',
        background: config.colors.warning,
        className: 'notyf__warning',
        icon: {
          className: 'icon-base ti tabler-alert-triangle-filled icon-md text-white',
          tagName: 'i'
        }
      },
      {
        type: 'success',
        background: config.colors.success,
        className: 'notyf__success',
        icon: {
          className: 'icon-base ti tabler-circle-check-filled icon-md text-white',
          tagName: 'i'
        }
      },
      {
        type: 'error',
        background: config.colors.danger,
        className: 'notyf__error',
        icon: {
          className: 'icon-base ti tabler-xbox-x-filled icon-md text-white',
          tagName: 'i'
        }
      }
    ]
  });

});
