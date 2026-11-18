/**
 * Optimistic-lock conflict: someone else saved the record after this page loaded it, and the
 * server wrote nothing (error "version_conflict", or "version_required" from an old page).
 *
 * The old handlers reloaded the page as soon as the message was closed, which threw away
 * everything the user had typed. This dialog lets them choose instead:
 *   - 最新の内容を読み込む: reload the latest data (their input is dropped)
 *   - 編集を続ける (also Esc / click outside): keep the form as it is so they can copy their
 *     input; saving stays refused until they reload.
 * One dialog at a time: saves that conflict while it is open (auto-save) do not open another.
 *
 * window.resolveVersionConflict(responseData, onReload) -> true when responseData is a conflict
 *   (the dialog is shown; onReload — or a page reload when none is given — runs only when the
 *   user picks 最新の内容を読み込む), false otherwise.
 * window.askVersionConflictReload(message) -> Promise<boolean> (true = reload)
 */
(function (window) {
    'use strict';

    var DEFAULT_MESSAGE = '他のユーザーが先に更新しました。';
    var NOTE = '「最新の内容を読み込む」を押すと、入力中の内容は破棄されます。'
        + '「編集を続ける」を押すと入力内容は残りますが、最新の内容を読み込むまで保存できません。';
    var pending = null;

    function isConflict(responseData) {
        return !!responseData
            && (responseData.error === 'version_conflict' || responseData.error === 'version_required');
    }

    function ask(message) {
        if (pending) {
            return pending;
        }
        var msg = message || DEFAULT_MESSAGE;
        if (typeof window.hideHourglass === 'function') {
            try { window.hideHourglass(); } catch (e) { /* ignore */ }
        }
        var dialog;
        if (typeof window.Swal !== 'undefined' && window.Swal.fire) {
            dialog = window.Swal.fire({
                icon: 'warning',
                title: '保存できませんでした',
                text: msg + '\n' + NOTE,
                showCancelButton: true,
                confirmButtonText: '最新の内容を読み込む',
                cancelButtonText: '編集を続ける',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then(function (result) {
                return !!(result && result.isConfirmed);
            });
        } else {
            dialog = Promise.resolve(window.confirm(msg + '\n\n' + NOTE + '\n\n最新の内容を読み込みますか？'));
        }
        pending = dialog.then(function (reload) {
            pending = null;
            return reload;
        }, function () {
            pending = null;
            return false;
        });
        return pending;
    }

    function resolveVersionConflict(responseData, onReload) {
        if (!isConflict(responseData)) {
            return false;
        }
        if (pending) {
            // A dialog for this conflict is already open: its answer applies.
            return true;
        }
        ask(responseData.message).then(function (reload) {
            if (!reload) {
                return;
            }
            if (typeof onReload === 'function') {
                onReload();
            } else {
                window.location.reload();
            }
        });
        return true;
    }

    window.askVersionConflictReload = ask;
    window.resolveVersionConflict = resolveVersionConflict;
})(window);
