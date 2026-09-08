/**
 * 省エネへの図面共有確認 — shared by project detail / list / parent detail.
 * Gate: current dept is 意匠設計/設備設計/技術課設備 AND a sibling project under same parent
 * belongs to department 省エネ計算 (not parent.requests tag).
 */
(function (window) {
    'use strict';

    var SHARE_DEPT_NAMES = { '意匠設計': true, '設備設計': true, '技術課設備': true };
    var ENERGY_DEPT_NAME = '省エネ計算';

    var REASON_OPTIONS = [
        { value: 'waiting_assignee', label: '担当確認待ちのため' },
        { value: 'additional_revision', label: '追加修正依頼が発生しているため' },
        { value: 'other', label: 'その他（理由記載欄）' }
    ];

    function t(label) {
        if (typeof i18next !== 'undefined' && i18next.isInitialized) {
            return i18next.t(label) || label;
        }
        return label;
    }

    function isShareSourceDepartment(departmentName) {
        return !!SHARE_DEPT_NAMES[String(departmentName || '').trim()];
    }

    function isActiveProjectStatus(status) {
        var s = String(status || '').trim();
        return s !== 'cancelled' && s !== 'deleted';
    }

    function hasEnergySavingSibling(siblings, currentProjectId) {
        if (!Array.isArray(siblings) || !siblings.length) return false;
        var curId = currentProjectId != null ? String(currentProjectId) : '';
        return siblings.some(function (p) {
            if (!p) return false;
            if (curId && String(p.id) === curId) return false;
            if (!isActiveProjectStatus(p.status)) return false;
            return String(p.department_name || '').trim() === ENERGY_DEPT_NAME;
        });
    }

    function coerceEnergySiblingFlag(value) {
        return value === true || value === 1 || value === '1';
    }

    /**
     * @param {object} project
     * @param {object} [options]
     * @param {string} [options.departmentName]
     * @param {boolean|number|string} [options.hasEnergySibling]
     * @param {array} [options.siblings]
     */
    function needsEnergyDrawingShareConfirm(project, options) {
        if (!project) return false;
        options = options || {};
        var dept = options.departmentName != null && options.departmentName !== ''
            ? String(options.departmentName).trim()
            : String(project.department_name || '').trim();
        if (!isShareSourceDepartment(dept)) return false;

        // Prefer live sibling list when provided
        if (Array.isArray(options.siblings)) {
            if (hasEnergySavingSibling(options.siblings, project.id)) {
                return true;
            }
            if (options.siblings.length > 0) {
                return false;
            }
        }

        if (coerceEnergySiblingFlag(options.hasEnergySibling)) {
            return true;
        }
        return coerceEnergySiblingFlag(project.has_energy_sibling);
    }

    function reasonLabel(code) {
        for (var i = 0; i < REASON_OPTIONS.length; i++) {
            if (REASON_OPTIONS[i].value === code) {
                return t(REASON_OPTIONS[i].label);
            }
        }
        return code || '';
    }

    function formatEnergyDrawingShareLabel(project) {
        if (!project || !project.energy_drawing_share_status) return '';
        if (project.energy_drawing_share_status === 'shared') {
            return t('図面共有済');
        }
        var reason = reasonLabel(project.energy_drawing_share_reason);
        if (project.energy_drawing_share_reason === 'other' && project.energy_drawing_share_note) {
            reason = String(project.energy_drawing_share_note).trim() || reason;
        }
        return reason
            ? (t('未共有') + ': ' + reason)
            : t('未共有');
    }

    function formatEnergyDrawingShareBadgeClass(project) {
        if (!project || !project.energy_drawing_share_status) return '';
        return project.energy_drawing_share_status === 'shared' ? 'bg-success' : 'bg-warning text-dark';
    }

    function appendEnergyDrawingShareToFormData(formData, answer) {
        if (!formData || !answer || !answer.status) return;
        formData.append('energy_drawing_share_status', answer.status);
        if (answer.status === 'not_shared') {
            formData.append('energy_drawing_share_reason', answer.reason || '');
            formData.append('energy_drawing_share_note', answer.note || '');
        }
    }

    function applyEnergyDrawingShareToProject(project, answer) {
        if (!project || !answer || !answer.status) return;
        project.energy_drawing_share_status = answer.status;
        project.energy_drawing_share_reason = answer.status === 'not_shared' ? (answer.reason || '') : '';
        project.energy_drawing_share_note = answer.status === 'not_shared' ? (answer.note || '') : '';
        project.energy_drawing_share_at = answer.at || new Date().toISOString().slice(0, 19).replace('T', ' ');
    }

    function buildReasonSelectHtml() {
        var opts = REASON_OPTIONS.map(function (o) {
            return '<option value="' + o.value + '">' + t(o.label) + '</option>';
        }).join('');
        return ''
            + '<div class="text-start">'
            + '<label class="form-label">' + t('共有しない理由') + '</label>'
            + '<select id="energyShareReasonSelect" class="form-select mb-2">' + opts + '</select>'
            + '<textarea id="energyShareReasonNote" class="form-control d-none" rows="3" placeholder="' + t('理由を入力してください') + '"></textarea>'
            + '</div>';
    }

    function promptNotSharedReason() {
        return Swal.fire({
            icon: 'question',
            title: t('共有しない理由'),
            html: buildReasonSelectHtml(),
            showCancelButton: true,
            confirmButtonText: t('確認'),
            cancelButtonText: t('取消'),
            focusConfirm: false,
            didOpen: function () {
                var sel = document.getElementById('energyShareReasonSelect');
                var note = document.getElementById('energyShareReasonNote');
                if (!sel || !note) return;
                var sync = function () {
                    if (sel.value === 'other') {
                        note.classList.remove('d-none');
                    } else {
                        note.classList.add('d-none');
                        note.value = '';
                    }
                };
                sel.addEventListener('change', sync);
                sync();
            },
            preConfirm: function () {
                var sel = document.getElementById('energyShareReasonSelect');
                var note = document.getElementById('energyShareReasonNote');
                var reason = sel ? sel.value : '';
                var text = note ? String(note.value || '').trim() : '';
                if (!reason) {
                    Swal.showValidationMessage(t('理由を選択してください'));
                    return false;
                }
                if (reason === 'other' && !text) {
                    Swal.showValidationMessage(t('理由を入力してください'));
                    return false;
                }
                return { reason: reason, note: reason === 'other' ? text : '' };
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return null;
            return {
                status: 'not_shared',
                reason: result.value.reason,
                note: result.value.note || ''
            };
        });
    }

    /**
     * @returns {Promise<object|null|false>} answer object, null if not required, false if cancelled
     */
    function promptEnergyDrawingShareConfirm(project, options) {
        if (!needsEnergyDrawingShareConfirm(project, options)) {
            return Promise.resolve(null);
        }
        if (typeof Swal === 'undefined') {
            return Promise.resolve(null);
        }
        return Swal.fire({
            icon: 'question',
            title: t('省エネに図面を共有しましたか？'),
            text: t('実施図の作業完了に伴い、省エネへの図面共有状況を確認してください。'),
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: t('共有する'),
            denyButtonText: t('共有しない'),
            cancelButtonText: t('取消'),
            reverseButtons: true
        }).then(function (result) {
            if (result.isDismissed) {
                return false;
            }
            if (result.isConfirmed) {
                return { status: 'shared', reason: '', note: '' };
            }
            if (result.isDenied) {
                return promptNotSharedReason().then(function (answer) {
                    return answer === null ? false : answer;
                });
            }
            return false;
        });
    }

    function ensureEnergyDrawingShareAnswer(project, options) {
        return promptEnergyDrawingShareConfirm(project, options);
    }

    window.EnergyDrawingShare = {
        ENERGY_DEPT_NAME: ENERGY_DEPT_NAME,
        SHARE_DEPT_NAMES: SHARE_DEPT_NAMES,
        REASON_OPTIONS: REASON_OPTIONS,
        isShareSourceDepartment: isShareSourceDepartment,
        hasEnergySavingSibling: hasEnergySavingSibling,
        needsEnergyDrawingShareConfirm: needsEnergyDrawingShareConfirm,
        promptEnergyDrawingShareConfirm: promptEnergyDrawingShareConfirm,
        ensureEnergyDrawingShareAnswer: ensureEnergyDrawingShareAnswer,
        appendEnergyDrawingShareToFormData: appendEnergyDrawingShareToFormData,
        applyEnergyDrawingShareToProject: applyEnergyDrawingShareToProject,
        formatEnergyDrawingShareLabel: formatEnergyDrawingShareLabel,
        formatEnergyDrawingShareBadgeClass: formatEnergyDrawingShareBadgeClass,
        reasonLabel: reasonLabel
    };
})(window);
