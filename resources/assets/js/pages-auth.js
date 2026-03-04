'use strict';

document.addEventListener('DOMContentLoaded', function () {
  // Select all auth forms
  const forms = document.querySelectorAll('#formAuthentication, #formForgotPassword, #form_reset_password');

  if (!forms.length || typeof FormValidation === 'undefined') return;

  forms.forEach(form => {
    const fields = {};

    // EMAIL
    if (form.querySelector('[name="email"]')) {
      fields.email = {
        validators: {
          notEmpty: { message: 'Please enter your email' },
          emailAddress: { message: 'Please enter a valid email address' }
        }
      };
    }

    // PASSWORD
    if (form.querySelector('[name="password"]')) {
      fields.password = {
        validators: {
          notEmpty: { message: 'Please enter your password' },
          stringLength: { min: 8, message: 'Password must be at least 8 characters' }
        }
      };
    }

    // PASSWORD CONFIRMATION
    if (form.querySelector('[name="password_confirmation"]')) {
      fields.password_confirmation = {
        validators: {
          notEmpty: { message: 'Please confirm your password' },
          identical: {
            compare: () => form.querySelector('[name="password"]').value,
            message: 'Passwords do not match'
          }
        }
      };
    }

    FormValidation.formValidation(form, {
      fields,
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.form-control-validation'
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      },
      init: instance => {
        instance.on('plugins.message.placed', e => {
          if (e.element.parentElement.classList.contains('input-group')) {
            e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
          }
        });
      }
    });
  });
});
