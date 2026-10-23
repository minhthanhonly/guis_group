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
        // Format: id_:_content_:_isImportant[_:_label]
        var arr = note.trim().split('_:_');
        var text = arr.length > 1 ? arr[1] : note.trim();
        var label = arr.length > 3 ? arr.slice(3).join('_:_') : '';
        var decoded = decodeHtmlForNote(text);
        decoded = (decoded || '').replace(/\u00A0/g, ' ').replace(/&nbsp;/gi, ' ');
        var plain = decoded.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
        if (label && plain) return '[' + label + '] ' + plain;
        if (label) return '[' + label + ']';
        return plain;
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

function findCustomFieldColumnDefForExcel(colName) {
    if (!colName) return null;
    var defs = (typeof customFieldColumnDefinitions !== 'undefined' && Array.isArray(customFieldColumnDefinitions))
        ? customFieldColumnDefinitions
        : [];
    for (var i = 0; i < defs.length; i++) {
        if (defs[i] && defs[i].key === colName) return defs[i];
    }
    return null;
}

function splitCustomFieldChoiceValues(value) {
    return String(value == null ? '' : value)
        .split(',')
        .map(function(s) { return s.trim(); })
        .filter(Boolean);
}

function joinCustomFieldChoiceValuesForExcel(items) {
    return items.map(function(item) {
        return typeof translateText === 'function' ? translateText(item) : item;
    }).join('、');
}

function formatCustomFieldChoiceForExcelExport(rowIndex, colName, innerHtml) {
    var def = findCustomFieldColumnDefForExcel(colName);
    if (!def) return null;
    var type = def.type;
    if (type !== 'checkbox' && type !== 'checkbox_select_all' && type !== 'radio') {
        return null;
    }

    var rawVal = '';
    if (projectTable && typeof rowIndex === 'number') {
        try {
            var rowData = projectTable.row(rowIndex).data();
            if (typeof getCustomFieldValueFromRow === 'function') {
                rawVal = getCustomFieldValueFromRow(rowData, def.label);
            } else if (rowData && rowData.custom_fields) {
                var raw = rowData.custom_fields;
                if (typeof raw === 'string' && raw.indexOf('&quot;') !== -1) {
                    raw = raw.replace(/&quot;/g, '"');
                }
                var arr = [];
                try {
                    arr = typeof raw === 'string' ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []);
                } catch (e) { arr = []; }
                var found = arr.find(function(f) {
                    return f && f.label && String(f.label).trim() === String(def.label).trim();
                });
                rawVal = found && found.value !== undefined ? found.value : '';
            }
        } catch (e) { /* ignore */ }
    }

    var items = splitCustomFieldChoiceValues(rawVal);
    if (items.length) {
        return joinCustomFieldChoiceValuesForExcel(items);
    }

    // Fallback: badge texts from rendered HTML
    if (!innerHtml) return '';
    var html = String(innerHtml);
    if (html.indexOf('<') === -1) {
        items = splitCustomFieldChoiceValues(html);
        return items.length ? joinCustomFieldChoiceValuesForExcel(items) : (html.trim() === '-' ? '' : html.trim());
    }
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    doc.querySelectorAll('.' + REMOVE_FOR_EXCEL_CLASS).forEach(function(el) {
        el.remove();
    });
    var badgeTexts = [];
    doc.querySelectorAll('.badge').forEach(function(el) {
        var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (text && text !== '-') badgeTexts.push(text);
    });
    if (badgeTexts.length) {
        return joinCustomFieldChoiceValuesForExcel(badgeTexts);
    }
    var plain = (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    return plain === '-' ? '' : plain;
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
                var choiceFormatted = formatCustomFieldChoiceForExcelExport(row, colName, inner);
                if (choiceFormatted !== null) {
                    return choiceFormatted;
                }
                return stripHtmlForExport(inner);
            }
        }
    };
}

var PROJECT_LIST_EXCEL_SS_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

function getProjectListExcelSheetDoc(sheet) {
    if (!sheet) return null;
    if (sheet.nodeType === 9) return sheet;
    if (sheet[0]) {
        return sheet[0].nodeType === 9 ? sheet[0] : (sheet[0].ownerDocument || null);
    }
    return sheet.ownerDocument || null;
}

function createProjectListExcelSsEl(doc, name) {
    if (!doc) return null;
    try {
        return doc.createElementNS(PROJECT_LIST_EXCEL_SS_NS, name);
    } catch (e) {
        return doc.createElement(name);
    }
}

function colLettersToIndexExcel(letters) {
    var n = 0;
    letters = String(letters || '').toUpperCase();
    for (var i = 0; i < letters.length; i++) {
        n = n * 26 + (letters.charCodeAt(i) - 64);
    }
    return n;
}

function colIndexToLettersExcel(index) {
    var s = '';
    while (index > 0) {
        var rem = (index - 1) % 26;
        s = String.fromCharCode(65 + rem) + s;
        index = Math.floor((index - 1) / 26);
    }
    return s;
}

function parseExcelCellRef(ref) {
    var m = String(ref || '').match(/^([A-Z]+)(\d+)$/i);
    if (!m) return null;
    return { col: colLettersToIndexExcel(m[1]), row: parseInt(m[2], 10) };
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

    var doc = getProjectListExcelSheetDoc(sheet);
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

    var emptyBorderStyle = String(getBorderedStyle(0));
    var maxCol = 0;
    var maxRow = 0;
    $('row c', sheet).each(function() {
        var pos = parseExcelCellRef($(this).attr('r'));
        if (!pos) return;
        if (pos.col > maxCol) maxCol = pos.col;
        if (pos.row > maxRow) maxRow = pos.row;
    });
    if (!maxCol || !maxRow || !doc) {
        // Fallback: border existing cells only
        $('row c', sheet).each(function() {
            var cell = $(this);
            cell.attr('s', getBorderedStyle(cell.attr('s')));
        });
        return;
    }

    var sheetDataEl = $('sheetData', sheet)[0];
    if (!sheetDataEl) return;

    function findRowEl(rowNum) {
        var rows = sheetDataEl.getElementsByTagName('row');
        for (var i = 0; i < rows.length; i++) {
            if (String(rows[i].getAttribute('r')) === String(rowNum)) return rows[i];
        }
        return null;
    }

    function findCellEl(rowEl, ref) {
        var cells = rowEl.getElementsByTagName('c');
        for (var i = 0; i < cells.length; i++) {
            if (cells[i].getAttribute('r') === ref) return cells[i];
        }
        return null;
    }

    function insertSortedChild(parent, child, attrName, value, toNumber) {
        var children = parent.childNodes;
        for (var i = 0; i < children.length; i++) {
            var node = children[i];
            if (!node || node.nodeType !== 1) continue;
            var raw = node.getAttribute(attrName);
            if (raw == null) continue;
            var cmp = toNumber ? parseInt(raw, 10) : colLettersToIndexExcel(String(raw).replace(/\d+/g, ''));
            var cur = toNumber ? value : colLettersToIndexExcel(String(value).replace(/\d+/g, ''));
            if (cmp > cur) {
                parent.insertBefore(child, node);
                return;
            }
        }
        parent.appendChild(child);
    }

    for (var rowNum = 1; rowNum <= maxRow; rowNum++) {
        var rowEl = findRowEl(rowNum);
        if (!rowEl) {
            rowEl = createProjectListExcelSsEl(doc, 'row');
            rowEl.setAttribute('r', String(rowNum));
            insertSortedChild(sheetDataEl, rowEl, 'r', rowNum, true);
        }

        for (var colNum = 1; colNum <= maxCol; colNum++) {
            var ref = colIndexToLettersExcel(colNum) + rowNum;
            var cellEl = findCellEl(rowEl, ref);
            if (!cellEl) {
                cellEl = createProjectListExcelSsEl(doc, 'c');
                cellEl.setAttribute('r', ref);
                cellEl.setAttribute('s', emptyBorderStyle);
                insertSortedChild(rowEl, cellEl, 'r', ref, false);
            } else {
                cellEl.setAttribute('s', String(getBorderedStyle(cellEl.getAttribute('s'))));
            }
        }
    }
}

function applyProjectListExcelColumnWidths(xlsx) {
    if (!xlsx || !xlsx.xl || typeof $ === 'undefined') return;
    var sheet = null;
    var worksheetKeys = xlsx.xl.worksheets ? Object.keys(xlsx.xl.worksheets) : [];
    if (worksheetKeys.length) {
        sheet = xlsx.xl.worksheets[worksheetKeys[0]];
    }
    if (!sheet) return;

    var doc = getProjectListExcelSheetDoc(sheet);
    var sharedStrings = xlsx.xl['sharedStrings.xml'] || null;
    var MIN_WIDTH = 6;
    var MAX_WIDTH = 48;
    var PAD = 2;

    function getCellText(cell) {
        var t = cell.getAttribute('t');
        if (t === 'inlineStr') {
            var texts = cell.getElementsByTagName('t');
            var out = '';
            for (var i = 0; i < texts.length; i++) out += texts[i].textContent || '';
            return out;
        }
        var vNodes = cell.getElementsByTagName('v');
        if (!vNodes.length) return '';
        var raw = vNodes[0].textContent || '';
        if (t === 's' && sharedStrings) {
            var idx = parseInt(raw, 10);
            if (isNaN(idx)) return '';
            var si = $('si', sharedStrings).eq(idx);
            if (!si.length) return '';
            return si.find('t').map(function() { return $(this).text() || ''; }).get().join('') || (si.text() || '');
        }
        return raw;
    }

    function measureDisplayWidth(text) {
        text = String(text || '');
        if (!text) return 0;
        var lines = text.split(/\r?\n/);
        var max = 0;
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var w = 0;
            for (var j = 0; j < line.length; j++) {
                var code = line.charCodeAt(j);
                if (
                    (code >= 0x1100 && code <= 0x115F) ||
                    (code >= 0x2E80 && code <= 0xA4CF) ||
                    (code >= 0xAC00 && code <= 0xD7A3) ||
                    (code >= 0xF900 && code <= 0xFAFF) ||
                    (code >= 0xFE10 && code <= 0xFE6F) ||
                    (code >= 0xFF00 && code <= 0xFF60) ||
                    (code >= 0xFFE0 && code <= 0xFFE6)
                ) {
                    w += 2;
                } else {
                    w += 1;
                }
            }
            if (w > max) max = w;
        }
        return max;
    }

    var colWidths = {};
    var maxCol = 0;
    $('row c', sheet).each(function() {
        var cell = this;
        var pos = parseExcelCellRef(cell.getAttribute('r'));
        if (!pos) return;
        if (pos.col > maxCol) maxCol = pos.col;
        var w = measureDisplayWidth(getCellText(cell));
        if (!colWidths[pos.col] || w > colWidths[pos.col]) {
            colWidths[pos.col] = w;
        }
    });
    if (!maxCol || !doc) return;

    var worksheetEl = sheet.documentElement || $('worksheet', sheet)[0] || null;
    if (!worksheetEl) return;

    var colsEl = worksheetEl.getElementsByTagName('cols')[0] || null;
    if (!colsEl) {
        colsEl = createProjectListExcelSsEl(doc, 'cols');
        var sheetDataEl = worksheetEl.getElementsByTagName('sheetData')[0];
        if (sheetDataEl) {
            worksheetEl.insertBefore(colsEl, sheetDataEl);
        } else {
            worksheetEl.appendChild(colsEl);
        }
    }

    // Replace col children safely in SpreadsheetML namespace
    while (colsEl.firstChild) {
        colsEl.removeChild(colsEl.firstChild);
    }
    for (var col = 1; col <= maxCol; col++) {
        var contentW = colWidths[col] || 0;
        var width = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, contentW + PAD));
        var colEl = createProjectListExcelSsEl(doc, 'col');
        colEl.setAttribute('min', String(col));
        colEl.setAttribute('max', String(col));
        colEl.setAttribute('width', width.toFixed(2));
        colEl.setAttribute('customWidth', '1');
        colsEl.appendChild(colEl);
    }
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
            try {
                applyProjectListExcelBorders(xlsx);
                applyProjectListExcelColumnWidths(xlsx);
            } catch (err) {
                console.error('Excel customize failed:', err);
            }
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
