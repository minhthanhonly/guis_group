/**
 *  Pages Authentication
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  (() => {
    const formAuthentication = document.querySelector('#formAuthentication');

    if (window.Helpers) {
      if (typeof window.Helpers.setTheme === 'function') {
        window.Helpers.setTheme(window.Helpers.getPreferredTheme());
      }
      if (typeof window.Helpers.switchImage === 'function') {
        const templateName = window.templateName || document.documentElement.getAttribute('data-template');
        const storedStyle =
          localStorage.getItem('templateCustomizer-' + templateName + '--Theme') ||
          (window.templateCustomizer?.settings?.defaultStyle ?? document.documentElement.getAttribute('data-bs-theme'));
        window.Helpers.switchImage(storedStyle || 'dark');
      }
    }

    document.querySelectorAll('.form-password-toggle').forEach(function (wrap) {
      const toggle = wrap.querySelector('.input-group-text');
      if (!toggle || toggle._passwordToggleBound) return;
      toggle._passwordToggleBound = true;
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        const input = wrap.querySelector('input');
        const icon = wrap.querySelector('i');
        if (!input || !icon) return;
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        icon.classList.toggle('tabler-eye', showPassword);
        icon.classList.toggle('tabler-eye-off', !showPassword);
      });
    });

    // Form validation for Add new record
    if (formAuthentication && typeof FormValidation !== 'undefined') {
      FormValidation.formValidation(formAuthentication, {
        fields: {
          username: {
            validators: {
              notEmpty: {
                message: 'ユーザー名を入力してください。'
              }
            }
          },
          email: {
            validators: {
              notEmpty: {
                message: 'メールアドレスを入力してください。'
              },
              emailAddress: {
                message: '有効なメールアドレスを入力してください。'
              }
            }
          },
          'userid': {
            validators: {
              notEmpty: {
                message: 'ユーザー名を入力してください。'
              }
            }
          },
          password: {
            validators: {
              notEmpty: {
                message: 'パスワードを入力してください。'
              }
            }
          },
          'confirm-password': {
            validators: {
              notEmpty: {
                message: 'パスワードを確認してください。'
              },
              identical: {
                compare: () => formAuthentication.querySelector('[name="password"]').value,
                message: 'パスワードと確認用パスワードが一致しません。'
              }
            }
          },
          terms: {
            validators: {
              notEmpty: {
                message: '利用規約に同意してください。'
              }
            }
          }
        },
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
    }

    // Two Steps Verification for numeral input mask
    const numeralMaskElements = document.querySelectorAll('.numeral-mask');

    // Format function for numeral mask
    const formatNumeral = value => value.replace(/\D/g, ''); // Only keep digits

    if (numeralMaskElements.length > 0) {
      numeralMaskElements.forEach(numeralMaskEl => {
        numeralMaskEl.addEventListener('input', event => {
          numeralMaskEl.value = formatNumeral(event.target.value);
        });
      });
    }
  })();
});