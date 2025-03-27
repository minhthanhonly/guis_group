/**
 *  Pages Authentication
 */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
  (() => {
    const formAuthentication = document.querySelector('#formAuthentication');

    // Form validation for Add new record
    if (formAuthentication && typeof FormValidation !== 'undefined') {
      FormValidation.formValidation(formAuthentication, {
        fields: {
          username: {
            validators: {
              notEmpty: {
                message: 'ユーザー名を入力してください。'
              },
              stringLength: {
                min: 6,
                message: 'ユーザー名は6文字以上で入力してください。'
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
              },
              stringLength: {
                min: 4,
                message: 'ユーザー名は6文字以上で入力してください。'
              }
            }
          },
          password: {
            validators: {
              notEmpty: {
                message: 'パスワードを入力してください。'
              },
              stringLength: {
                min: 6,
                message: 'パスワードは6文字以上で入力してください。'
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
              },
              stringLength: {
                min: 6,
                message: 'パスワードは6文字以上で入力してください。'
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