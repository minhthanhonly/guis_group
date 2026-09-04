/**
 * Show API error with response payload (same pattern as project-list.js showProjectListError).
 */
(function(window) {
    'use strict';

    function escapeHtmlForParentProjectError(s) {
        if (s == null || s === '') return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function extractParentProjectServerPayload(errorOrResponse) {
        if (!errorOrResponse) return null;
        if (errorOrResponse.response && errorOrResponse.response.data != null) {
            return errorOrResponse.response.data;
        }
        if (errorOrResponse.data != null) {
            return errorOrResponse.data;
        }
        if (typeof errorOrResponse === 'object') {
            return errorOrResponse;
        }
        return null;
    }

    function formatParentProjectServerDebugInfo(payload) {
        if (payload == null) return '';
        if (typeof payload === 'string') return payload;
        try {
            return JSON.stringify(payload, null, 2);
        } catch (e) {
            return String(payload);
        }
    }

    function showParentProjectError(message, errorOrResponse, options) {
        options = options || {};
        var payload = extractParentProjectServerPayload(errorOrResponse);
        var msg = message || 'エラーが発生しました。';
        if (payload && typeof payload === 'object') {
            var serverMsg = payload.message || payload.error || payload.message_code;
            if (serverMsg && String(serverMsg) !== String(msg)) {
                msg += '\n\n' + serverMsg;
            }
        }
        var debugText = formatParentProjectServerDebugInfo(payload);
        if (typeof hideHourglass === 'function') {
            hideHourglass();
        }
        if (typeof Swal !== 'undefined' && Swal.fire) {
            var swalOptions = {
                title: 'Error!',
                icon: 'error',
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
            };
            if (debugText) {
                swalOptions.html = '<div class="text-start">' + escapeHtmlForParentProjectError(msg).replace(/\n/g, '<br>') + '</div>' +
                    '<pre class="text-start small mt-3 mb-0 p-2 bg-light border rounded" style="max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-word;">' +
                    escapeHtmlForParentProjectError(debugText) + '</pre>';
            } else {
                swalOptions.text = msg;
            }
            Swal.fire(swalOptions).then(function() {
                if (typeof options.onClose === 'function') {
                    options.onClose();
                }
            });
            return;
        }
        if (typeof showMessage === 'function') {
            showMessage(debugText ? (msg + '\n\n' + debugText) : msg, true);
        } else if (typeof alert === 'function') {
            alert(debugText ? (msg + '\n\n' + debugText) : msg);
        }
        if (typeof options.onClose === 'function') {
            options.onClose();
        }
    }

    window.showParentProjectError = showParentProjectError;
    window.extractParentProjectServerPayload = extractParentProjectServerPayload;
})(window);
