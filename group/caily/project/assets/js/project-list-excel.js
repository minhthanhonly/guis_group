/**
 * Project list Excel export helpers (lazy-loaded).
 * Classic script — shares globals with project-list.js (projectTable, teamIdToName, etc.).
 */
(function() {
    'use strict';
    if (window.ProjectListExcelInstalled) return;

var isProjectListExporting = false;
var projectListExcelButtons = null;
var REMOVE_FOR_EXCEL_CLASS = 'removeForExcel';

function isProjectListExcelExportAvailable() {
    return typeof $ !== 'undefined'
        && !!($.fn && $.fn.dataTable && $.fn.dataTable.Buttons);
}

function parseTeamLabelsFromExportHtml(inner) {
    if (!inner) return [];
    inner = String(inner);
    if (inner.indexOf('<') === -1) {
        var plain = inner.trim();
        return plain ? [plain] : [];
    }
    var parser = new DOMParser();
    var doc = parser.parseFromString(inner, 'text/html');
    var labels = [];
    doc.querySelectorAll('.badge').forEach(function(el) {
        var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (text) labels.push(text);
    });
    return labels;
}

function formatTeamsForExcelExport(rowIndex, innerHtml) {
    var data = null;
    if (projectTable && typeof rowIndex === 'number') {
        try {
            var rowData = projectTable.row(rowIndex).data();
            data = rowData && rowData.teams;
        } catch (e) {}
    }
    if (!data || data === '') {
        var labelsFromHtml = parseTeamLabelsFromExportHtml(innerHtml);
        var unassignedLabel = typeof translateText === 'function' ? translateText('未割り当て') : '未割り当て';
        if (labelsFromHtml.length) {
            if (labelsFromHtml.length === 1 && labelsFromHtml[0] === unassignedLabel) {
                return unassignedLabel;
            }
            return labelsFromHtml.join(', ');
        }
        return unassignedLabel;
    }
    var ids = typeof data === 'string'
        ? data.split(',').map(function(item) { return item.trim(); }).filter(Boolean)
        : [String(data)];
    if (!ids.length) {
        return typeof translateText === 'function' ? translateText('未割り当て') : '未割り当て';
    }
    return ids.map(function(id) {
        var label = teamIdToName[id] || id;
        return String(label).replace(/CL意匠/g, 'CL_').replace(/G意匠/g, 'G_');
    }).join(', ');
}

function parseConfirmationNotesRawForExport(cellData) {
    if (!cellData || cellData === '') return '';
    var notes = String(cellData).split('_|_').filter(function(note) {
        return note.trim() !== '';
    });
    if (!notes.length) return '';
    var texts = notes.map(function(note) {
        var arr = note.trim().split('_:_');
        var text = arr.length > 1 ? arr[1] : note.trim();
        var decoded = decodeHtmlForNote(text);
        decoded = (decoded || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
        return decoded.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
    }).filter(function(text) {
        return text && text !== '-';
    });
    return texts.join('\n');
}

function parseConfirmationNotesFromExportHtml(inner) {
    if (!inner) return '';
    inner = String(inner);
    if (inner.indexOf('<') === -1) {
        var plain = inner.trim();
        return plain === '-' ? '' : plain;
    }
    var parser = new DOMParser();
    var doc = parser.parseFromString(inner, 'text/html');
    var texts = [];
    doc.querySelectorAll('.note-text').forEach(function(el) {
        var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (text && text !== '-') texts.push(text);
    });
    return texts.join('\n');
}

function formatConfirmationNotesForExcelExport(rowIndex, innerHtml, fieldName) {
    var data = null;
    if (projectTable && typeof rowIndex === 'number' && fieldName) {
        try {
            var rowData = projectTable.row(rowIndex).data();
            data = rowData && rowData[fieldName];
        } catch (e) { /* ignore */ }
    }
    var fromRaw = parseConfirmationNotesRawForExport(data);
    if (fromRaw) return fromRaw;
    return parseConfirmationNotesFromExportHtml(innerHtml);
}

function getProjectListExcelExportOptions() {
    return {
        columns: function(idx) {
            if (!projectTable) return false;
            var col = projectTable.column(idx);
            var name = '';
            try {
                name = typeof col.name === 'function' ? col.name() : '';
            } catch (e) {}
            if (!name && projectTable.settings()[0] && projectTable.settings()[0].aoColumns[idx]) {
                name = projectTable.settings()[0].aoColumns[idx].name || '';
            }
            if (name === 'is_favorite') return false;
            if (isProjectDirectorColumn(name) && !canViewProjectDirectorColumns()) return false;
            return col.visible();
        },
        format: {
            body: function(inner, row, column) {
                var colName = '';
                if (projectTable) {
                    try {
                        colName = projectTable.column(column).name() || '';
                    } catch (e) {}
                    if (!colName && projectTable.settings()[0] && projectTable.settings()[0].aoColumns[column]) {
                        colName = projectTable.settings()[0].aoColumns[column].name || '';
                    }
                }
                if (colName === 'teams') {
                    return formatTeamsForExcelExport(row, inner);
                }
                if (colName === 'confirmation_notes_caily') {
                    return formatConfirmationNotesForExcelExport(row, inner, 'confirmation_notes_caily');
                }
                if (colName === 'confirmation_notes_guis') {
                    return formatConfirmationNotesForExcelExport(row, inner, 'confirmation_notes_guis');
                }
                return stripHtmlForExport(inner);
            }
        }
    };
}

function applyProjectListExcelBorders(xlsx) {
    if (!xlsx || !xlsx.xl || typeof $ === 'undefined') return;
    var styleSheet = xlsx.xl['styles.xml'];
    var sheet = null;
    var worksheetKeys = xlsx.xl.worksheets ? Object.keys(xlsx.xl.worksheets) : [];
    if (worksheetKeys.length) {
        sheet = xlsx.xl.worksheets[worksheetKeys[0]];
    }
    if (!sheet || !styleSheet) return;

    var styleCache = {};

    function getBorderedStyle(styleIndex) {
        styleIndex = parseInt(styleIndex || '0', 10);
        if (styleCache[styleIndex] !== undefined) {
            return styleCache[styleIndex];
        }

        var baseXf = $('cellXfs xf', styleSheet).eq(styleIndex);
        var fontId = baseXf.attr('fontId') || '0';
        var fillId = baseXf.attr('fillId') || '0';
        var numFmtId = baseXf.attr('numFmtId') || '0';
        var xfId = baseXf.attr('xfId') || '0';
        var extraAttrs = '';
        if (baseXf.attr('applyFont')) extraAttrs += ' applyFont="1"';
        if (baseXf.attr('applyFill')) extraAttrs += ' applyFill="1"';
        if (baseXf.attr('applyNumberFormat')) extraAttrs += ' applyNumberFormat="1"';
        if (baseXf.attr('applyAlignment')) extraAttrs += ' applyAlignment="1"';

        var borderId = $('border', styleSheet).length;
        $('borders', styleSheet).append(
            '<border><left style="thin"><color auto="1"/></left>' +
            '<right style="thin"><color auto="1"/></right>' +
            '<top style="thin"><color auto="1"/></top>' +
            '<bottom style="thin"><color auto="1"/></bottom></border>'
        );
        $('borders', styleSheet).attr('count', borderId + 1);

        var newIndex = $('cellXfs xf', styleSheet).length;
        $('cellXfs', styleSheet).append(
            '<xf numFmtId="' + numFmtId + '" fontId="' + fontId + '" fillId="' + fillId +
            '" borderId="' + borderId + '" xfId="' + xfId + '"' + extraAttrs + ' applyBorder="1"/>'
        );
        $('cellXfs', styleSheet).attr('count', newIndex + 1);
        styleCache[styleIndex] = newIndex;
        return newIndex;
    }

    $('row c', sheet).each(function() {
        var cell = $(this);
        cell.attr('s', getBorderedStyle(cell.attr('s')));
    });
}

function getProjectListExcelButtonConfig() {
    return {
        extend: 'excel',
        className: 'buttons-project-excel-export d-none',
        title: '',
        filename: function() {
            return getProjectListExcelFilename();
        },
        exportOptions: getProjectListExcelExportOptions(),
        customize: function(xlsx) {
            applyProjectListExcelBorders(xlsx);
        }
    };
}

function destroyProjectListExcelButtons() {
    if (projectListExcelButtons) {
        try {
            projectListExcelButtons.destroy();
        } catch (e) {
            /* ignore */
        }
        projectListExcelButtons = null;
    }
}

function ensureProjectListExcelButtons(dt) {
    if (!dt || !isProjectListExcelExportAvailable()) {
        return false;
    }
    destroyProjectListExcelButtons();
    projectListExcelButtons = new $.fn.dataTable.Buttons(dt, {
        buttons: [getProjectListExcelButtonConfig()]
    });
    return true;
}

function stripHtmlForExport(inner) {
    if (inner === null || inner === undefined) return '';
    inner = String(inner);
    if (!inner.length) return inner;
    if (inner.indexOf('<') === -1) return inner.trim();
    var parser = new DOMParser();
    var doc = parser.parseFromString(inner, 'text/html');
    doc.querySelectorAll('.' + REMOVE_FOR_EXCEL_CLASS).forEach(function(el) {
        el.remove();
    });
    return (doc.body.textContent || doc.body.innerText || '').replace(/\s+/g, ' ').trim();
}

function getProjectListExcelFilename() {
    var depName = (window.app && window.app.selectedDepartment && window.app.selectedDepartment.name)
        ? String(window.app.selectedDepartment.name).replace(/[\\/:*?"<>|]/g, '_')
        : 'project_list';
    var stamp = (typeof moment !== 'undefined') ? moment().format('YYYYMMDD_HHmmss') : String(Date.now());
    return depName + '_' + stamp;
}

function exportProjectListToExcel() {
    if (isProjectListExporting) return;
    if (!projectTable || !$.fn.DataTable.isDataTable('#projectTable')) {
        if (typeof showMessage === 'function') {
            showProjectListError('テーブルが読み込まれていません。');
        }
        return;
    }
    if (!isProjectListExcelExportAvailable()) {
        if (typeof showMessage === 'function') {
            showProjectListError('Excel出力機能が利用できません。');
        }
        return;
    }

    ensureProjectListJszip().then(function() {
        exportProjectListToExcelAfterJszip();
    }).catch(function(err) {
        console.error('Failed to load JSZip:', err);
        if (typeof showMessage === 'function') {
            showProjectListError('Excel出力機能が利用できません。');
        }
    });
}

function exportProjectListToExcelAfterJszip() {
    var dt = projectTable;
    if (!ensureProjectListExcelButtons(dt)) {
        if (typeof showMessage === 'function') {
            showProjectListError('Excel出力機能が利用できません。');
        }
        return;
    }

    isProjectListExporting = true;
    if (window.app) window.app.loading = true;

    var pageInfo = dt.page.info();
    var oldStart = pageInfo.start;
    var oldLength = dt.page.len();
    var exportLength = pageInfo.recordsDisplay || pageInfo.recordsTotal || oldLength;
    if (!exportLength || exportLength < 1) {
        isProjectListExporting = false;
        if (window.app) window.app.loading = false;
        if (typeof showMessage === 'function') {
            showProjectListError('出力するデータがありません。');
        }
        return;
    }
    var restored = false;

    function restorePagination() {
        if (restored) return;
        restored = true;
        dt.one('preXhr', function(e, settings, data) {
            data.start = oldStart;
            data.length = oldLength;
        });
        dt.one('draw', function() {
            isProjectListExporting = false;
            if (window.app) window.app.loading = false;
        });
        dt.ajax.reload(null, false);
    }

    dt.one('preXhr', function(e, settings, data) {
        data.start = 0;
        data.length = exportLength;
    });

    dt.one('error.dt', function() {
        if (!restored) {
            isProjectListExporting = false;
            if (window.app) window.app.loading = false;
            if (typeof showMessage === 'function') {
                showProjectListError('Excel出力に失敗しました。');
            }
        }
    });

    dt.one('draw', function() {
        try {
            dt.button(0).trigger();
        } catch (err) {
            console.error('Excel export failed:', err);
            if (typeof showMessage === 'function') {
                showProjectListError('Excel出力に失敗しました。', err);
            }
            isProjectListExporting = false;
            if (window.app) window.app.loading = false;
            restored = true;
            return;
        }
        restorePagination();
    });

    dt.ajax.reload();
}

    window.exportProjectListToExcel = exportProjectListToExcel;
    window.exportProjectListToExcelAfterJszip = exportProjectListToExcelAfterJszip;
    window.ensureProjectListExcelButtons = ensureProjectListExcelButtons;
    window.destroyProjectListExcelButtons = destroyProjectListExcelButtons;
    window.__exportProjectListToExcelImpl = exportProjectListToExcel;
    window.__exportProjectListToExcelAfterJszipImpl = exportProjectListToExcelAfterJszip;
    window.__ensureProjectListExcelButtonsImpl = ensureProjectListExcelButtons;
    window.__destroyProjectListExcelButtonsImpl = destroyProjectListExcelButtons;
    window.ProjectListExcelInstalled = true;
})();
