(function() {
    const STORAGE_KEY_MONTH = 'revenue_statistics_selected_month';
    const STORAGE_KEY_DEPT = 'revenue_statistics_selected_department';
    const VALID_SUB_TABS = ['summary', 'detail', 'backlog', 'estimated', 'cancelled'];

    function escapeHtml(text) {
        if (text == null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatCompanyNameLabel(companyName) {
        var company = String(companyName || '').trim();
        if (!company || company === '（未設定）') return '';

        var text = '他社';
        var style = 'font-size: 0.65rem; vertical-align: middle;';
        if (company.indexOf('大東建託') !== -1) {
            text = '大東';
            style += ' background-color: #dc3545; color: #fff;';
        } else if (company.indexOf('東建コーポレーション') !== -1) {
            text = '東建';
            style += ' background-color: #8B4513; color: #fff;';
        } else {
            style += ' background-color: #0d6efd; color: #fff;';
        }
        return '<span class="badge me-1" style="' + style + '">' + escapeHtml(text) + '</span>';
    }

    function sumProjectField(projects, field) {
        return (projects || []).reduce(function(sum, p) {
            const n = Number(p[field]);
            return sum + (Number.isFinite(n) ? n : 0);
        }, 0);
    }

    function groupProjectsByCompany(projects, amountFields) {
        const map = {};
        const order = [];
        (projects || []).forEach(function(p) {
            const key = (p.company_name && String(p.company_name).trim()) ? String(p.company_name).trim() : '（未設定）';
            if (!map[key]) {
                map[key] = [];
                order.push(key);
            }
            map[key].push(p);
        });
        return order.map(function(company_name) {
            const groupProjects = map[company_name];
            const totals = { count: groupProjects.length };
            (amountFields || []).forEach(function(field) {
                totals[field] = sumProjectField(groupProjects, field);
            });
            return { company_name: company_name, projects: groupProjects, totals: totals };
        });
    }

    function currentYearMonth() {
        const d = new Date();
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        return y + '-' + m;
    }

    function addMonthsToYearMonth(yearMonth, delta) {
        const parts = String(yearMonth || '').split('-');
        let year = parseInt(parts[0], 10);
        let month = parseInt(parts[1], 10);
        if (!Number.isFinite(year) || !Number.isFinite(month)) return yearMonth;

        month += delta;
        while (month > 12) {
            month -= 12;
            year += 1;
        }
        while (month < 1) {
            month += 12;
            year -= 1;
        }
        return year + '-' + String(month).padStart(2, '0');
    }

    function buildMonthOptions(monthsBefore, monthsAfter) {
        const current = currentYearMonth();
        const months = [];
        const before = Number.isFinite(monthsBefore) ? monthsBefore : 18;
        const after = Number.isFinite(monthsAfter) ? monthsAfter : 12;

        for (let i = -before; i <= after; i++) {
            const month = addMonthsToYearMonth(current, i);
            months.push({
                month: month,
                month_label: buildMonthLabel(month)
            });
        }

        return months.sort(function(a, b) {
            return String(b.month).localeCompare(String(a.month));
        });
    }

    function buildMonthLabel(month) {
        if (!month || !/^\d{4}-\d{2}$/.test(month)) return month || '';
        const parts = month.split('-');
        return parts[0] + '年' + parts[1] + '月';
    }

    function normalizeSubTab(tab) {
        const value = String(tab || '').trim();
        return VALID_SUB_TABS.indexOf(value) !== -1 ? value : 'summary';
    }

    function emptySummaryTotals() {
        return {
            project_count: 0,
            estimate_count: 0,
            estimate_amount: 0,
            invoice_count: 0,
            invoice_amount: 0,
            payment_count: 0,
            payment_amount: 0
        };
    }

    function translateLabel(key) {
        return typeof translateText === 'function' ? translateText(key) : key;
    }

    function excelDash(val) {
        if (val == null || val === '') return '—';
        return val;
    }

    function excelAmount(val) {
        const n = Number(val);
        return Number.isFinite(n) ? Math.round(n) : 0;
    }

    function sanitizeExcelFilenamePart(text) {
        return String(text || '')
            .replace(/[\\/:*?"<>|]/g, '_')
            .replace(/\s+/g, '_')
            .substring(0, 80) || 'export';
    }

    const REVENUE_EXPORT_CONFIG = {
        detail: {
            groupsComputed: 'invoicedGroups',
            sheetName: '請求済み案件',
            filenamePrefix: '請求済み案件',
            headers: ['ID', '案件名', '支店名', '工事番号', '担当', '受注形態', '規模', '期限日', 'チーム', '請求日', '請求金額', '決済備考'],
            mapRow(p, vm) {
                return [
                    p.id,
                    excelDash(p.name),
                    excelDash(p.branch_name),
                    excelDash(p.parent_construction_number),
                    excelDash(p.tantou),
                    excelDash(p.project_order_type),
                    excelDash(p.parent_scale),
                    vm.formatDate(p.end_date),
                    vm.formatProjectTeams(p),
                    vm.formatDate(p.invoice_date),
                    excelAmount(p.invoice_amount),
                    excelDash(p.payment_note)
                ];
            }
        },
        backlog: {
            groupsComputed: 'backlogGroups',
            sheetName: '完了未請求',
            filenamePrefix: '完了未請求',
            headers: ['ID', '案件名', '支店名', '工事番号', '担当', '受注形態', '規模', '期限日', 'チーム', '見積金額', '完了日', '決済備考'],
            mapRow(p, vm) {
                return [
                    p.id,
                    excelDash(p.name),
                    excelDash(p.branch_name),
                    excelDash(p.parent_construction_number),
                    excelDash(p.tantou),
                    excelDash(p.project_order_type),
                    excelDash(p.parent_scale),
                    vm.formatDate(p.end_date),
                    vm.formatProjectTeams(p),
                    excelAmount(p.amount),
                    vm.formatDate(p.actual_end_date),
                    excelDash(p.payment_note)
                ];
            }
        },
        estimated: {
            groupsComputed: 'estimatedGroups',
            sheetName: '見積済未完了',
            filenamePrefix: '見積済未完了',
            headers: ['ID', '案件名', '支店名', '工事番号', '担当', '受注形態', '規模', '期限日', 'チーム', '見積金額', '見積日', '見積状況', '決済備考'],
            mapRow(p, vm) {
                return [
                    p.id,
                    excelDash(p.name),
                    excelDash(p.branch_name),
                    excelDash(p.parent_construction_number),
                    excelDash(p.tantou),
                    excelDash(p.project_order_type),
                    excelDash(p.parent_scale),
                    vm.formatDate(p.end_date),
                    vm.formatProjectTeams(p),
                    excelAmount(p.amount),
                    vm.formatDate(p.estimate_date),
                    excelDash(p.estimate_status),
                    excelDash(p.payment_note)
                ];
            }
        },
        cancelled: {
            groupsComputed: 'cancelledGroups',
            sheetName: 'キャンセル',
            filenamePrefix: 'キャンセル案件',
            headers: ['ID', '案件名', '支店名', '工事番号', '担当', '受注形態', '規模', '期限日', 'チーム', '見積金額', '決済備考'],
            mapRow(p, vm) {
                return [
                    p.id,
                    excelDash(p.name),
                    excelDash(p.branch_name),
                    excelDash(p.parent_construction_number),
                    excelDash(p.tantou),
                    excelDash(p.project_order_type),
                    excelDash(p.parent_scale),
                    vm.formatDate(p.end_date),
                    vm.formatProjectTeams(p),
                    excelAmount(p.amount),
                    excelDash(p.payment_note)
                ];
            }
        }
    };

    function buildRevenueStatsExcelRows(groups, config, vm, title) {
        const rows = [];
        const headers = (config.headers || []).map(translateLabel);
        const companyRowNumbers = [];
        const headerRowNumbers = [];

        if (title) {
            rows.push([title]);
        }

        (groups || []).forEach(function(group) {
            rows.push([]);
            rows.push([group.company_name]);
            companyRowNumbers.push(rows.length);
            rows.push(headers.slice());
            headerRowNumbers.push(rows.length);
            (group.projects || []).forEach(function(p) {
                rows.push(config.mapRow(p, vm));
            });
        });

        return {
            rows: rows,
            companyRowNumbers: companyRowNumbers,
            headerRowNumbers: headerRowNumbers
        };
    }

    function buildRevenueExportTitle(tabKey, vm) {
        switch (tabKey) {
            case 'detail':
                return translateLabel('請求済み案件') + '（' + vm.monthLabel + '）';
            case 'backlog':
                return translateLabel('完了・未請求案件(見積済)') + '（' + translateLabel('すべての期間') + '）';
            case 'estimated': {
                const period = vm.showEstimatedAllTime
                    ? translateLabel('すべての期間')
                    : vm.monthLabel;
                return translateLabel('見積済案件(未完了案件)') + '（' + period + '）';
            }
            case 'cancelled': {
                const period = vm.showCancelledAllTime
                    ? translateLabel('すべての期間')
                    : vm.monthLabel;
                return translateLabel('キャンセル案件') + '（' + period + '）';
            }
            default:
                return '';
        }
    }

    function findElementsByLocalName(root, localName) {
        const result = [];
        const nodes = root.getElementsByTagName('*');
        for (let i = 0; i < nodes.length; i++) {
            if (nodes[i].localName === localName) {
                result.push(nodes[i]);
            }
        }
        return result;
    }

    function findFirstElementByLocalName(root, localName) {
        const nodes = root.getElementsByTagName('*');
        for (let i = 0; i < nodes.length; i++) {
            if (nodes[i].localName === localName) {
                return nodes[i];
            }
        }
        return null;
    }

    function columnIndexToLetter(index) {
        let n = index + 1;
        let letters = '';
        while (n > 0) {
            const rem = (n - 1) % 26;
            letters = String.fromCharCode(65 + rem) + letters;
            n = Math.floor((n - 1) / 26);
        }
        return letters;
    }

    function getCellDisplayWidth(value) {
        if (value == null || value === '') return 0;
        const text = String(value);
        let width = 0;
        for (let i = 0; i < text.length; i++) {
            const code = text.charCodeAt(i);
            if (code > 255 || (code >= 0x3000 && code <= 0x9fff) || (code >= 0xff00 && code <= 0xffef)) {
                width += 2;
            } else {
                width += 1;
            }
        }
        return width;
    }

    function computeColumnWidths(rows, colCount, skipRows) {
        const maxWidths = new Array(colCount).fill(0);
        const start = Math.max(0, skipRows || 0);
        for (let r = start; r < rows.length; r++) {
            const row = rows[r] || [];
            for (let c = 0; c < colCount; c++) {
                const width = getCellDisplayWidth(row[c]);
                if (width > maxWidths[c]) maxWidths[c] = width;
            }
        }
        return maxWidths.map(function(width) {
            return Math.min(48, Math.max(5, width + 2));
        });
    }

    function applyRevenueStatsExcelSheetFormatting(stylesXml, sheetXml, options) {
        const parser = new DOMParser();
        const styleDoc = parser.parseFromString(stylesXml, 'application/xml');
        const sheetDoc = parser.parseFromString(sheetXml, 'application/xml');
        if (styleDoc.getElementsByTagName('parsererror').length || sheetDoc.getElementsByTagName('parsererror').length) {
            return { stylesXml: stylesXml, sheetXml: sheetXml };
        }

        const bordersEl = findFirstElementByLocalName(styleDoc.documentElement, 'borders');
        const cellXfsEl = findFirstElementByLocalName(styleDoc.documentElement, 'cellXfs');
        const sheetRoot = sheetDoc.documentElement;
        const sheetNs = sheetRoot.namespaceURI;
        const startRow = options && Number.isFinite(options.borderStartRow) ? options.borderStartRow : 1;
        const boldRows = {};
        (options && options.boldRows ? options.boldRows : []).forEach(function(rowNum) {
            boldRows[rowNum] = true;
        });

        if (options && options.columnWidths && options.columnWidths.length) {
            let colsEl = findFirstElementByLocalName(sheetRoot, 'cols');
            if (!colsEl) {
                colsEl = sheetDoc.createElementNS(sheetNs, 'cols');
                const sheetData = findFirstElementByLocalName(sheetRoot, 'sheetData');
                if (sheetData) {
                    sheetRoot.insertBefore(colsEl, sheetData);
                } else {
                    sheetRoot.insertBefore(colsEl, sheetRoot.firstChild);
                }
            } else {
                while (colsEl.firstChild) {
                    colsEl.removeChild(colsEl.firstChild);
                }
            }

            options.columnWidths.forEach(function(width, index) {
                const col = sheetDoc.createElementNS(sheetNs, 'col');
                const colNum = index + 1;
                col.setAttribute('min', String(colNum));
                col.setAttribute('max', String(colNum));
                col.setAttribute('width', String(Math.round(width * 10) / 10));
                col.setAttribute('customWidth', '1');
                colsEl.appendChild(col);
            });
        }

        if (options && options.titleMerge && options.titleMerge.colCount > 1) {
            const mergeRow = options.titleMerge.row || 1;
            const endCol = columnIndexToLetter(options.titleMerge.colCount - 1);
            const mergeRef = 'A' + mergeRow + ':' + endCol + mergeRow;
            let mergeCellsEl = findFirstElementByLocalName(sheetRoot, 'mergeCells');
            if (!mergeCellsEl) {
                mergeCellsEl = sheetDoc.createElementNS(sheetNs, 'mergeCells');
                sheetRoot.appendChild(mergeCellsEl);
            }
            const mergeCell = sheetDoc.createElementNS(sheetNs, 'mergeCell');
            mergeCell.setAttribute('ref', mergeRef);
            mergeCellsEl.appendChild(mergeCell);
            mergeCellsEl.setAttribute('count', String(findElementsByLocalName(mergeCellsEl, 'mergeCell').length));
        }

        if (!bordersEl || !cellXfsEl) {
            const serializer = new XMLSerializer();
            return {
                stylesXml: serializer.serializeToString(styleDoc),
                sheetXml: serializer.serializeToString(sheetDoc)
            };
        }

        const ns = bordersEl.namespaceURI;
        const styleCache = {};

        function createBorderSide(sideName) {
            const side = styleDoc.createElementNS(ns, sideName);
            side.setAttribute('style', 'thin');
            const color = styleDoc.createElementNS(ns, 'color');
            color.setAttribute('auto', '1');
            side.appendChild(color);
            return side;
        }

        function getStyledCellXf(styleIndex, useBorder, useBold) {
            styleIndex = parseInt(styleIndex || '0', 10);
            const cacheKey = [styleIndex, useBorder ? 1 : 0, useBold ? 1 : 0].join(':');
            if (styleCache[cacheKey] !== undefined) {
                return styleCache[cacheKey];
            }

            const xfs = findElementsByLocalName(cellXfsEl, 'xf');
            const baseXf = xfs[styleIndex] || xfs[0];
            if (!baseXf) {
                styleCache[cacheKey] = 0;
                return 0;
            }

            let borderId = baseXf.getAttribute('borderId') || '0';
            if (useBorder) {
                const border = styleDoc.createElementNS(ns, 'border');
                ['left', 'right', 'top', 'bottom'].forEach(function(sideName) {
                    border.appendChild(createBorderSide(sideName));
                });
                bordersEl.appendChild(border);

                borderId = String(findElementsByLocalName(bordersEl, 'border').length - 1);
                bordersEl.setAttribute('count', String(parseInt(borderId, 10) + 1));
            }

            let fontId = baseXf.getAttribute('fontId') || '0';
            if (useBold) {
                const fontsEl = findFirstElementByLocalName(styleDoc.documentElement, 'fonts');
                const baseFonts = findElementsByLocalName(fontsEl, 'font');
                const baseFont = baseFonts[parseInt(fontId, 10)] || baseFonts[0];
                if (fontsEl && baseFont) {
                    const boldFont = baseFont.cloneNode(true);
                    if (!findFirstElementByLocalName(boldFont, 'b')) {
                        boldFont.appendChild(styleDoc.createElementNS(ns, 'b'));
                    }
                    fontsEl.appendChild(boldFont);
                    fontId = String(findElementsByLocalName(fontsEl, 'font').length - 1);
                    fontsEl.setAttribute('count', String(parseInt(fontId, 10) + 1));
                }
            }

            const xf = styleDoc.createElementNS(ns, 'xf');
            xf.setAttribute('numFmtId', baseXf.getAttribute('numFmtId') || '0');
            xf.setAttribute('fontId', fontId);
            xf.setAttribute('fillId', baseXf.getAttribute('fillId') || '0');
            xf.setAttribute('borderId', borderId);
            xf.setAttribute('xfId', baseXf.getAttribute('xfId') || '0');
            if (baseXf.getAttribute('applyFont') || useBold) xf.setAttribute('applyFont', '1');
            if (baseXf.getAttribute('applyFill')) xf.setAttribute('applyFill', '1');
            if (baseXf.getAttribute('applyNumberFormat')) xf.setAttribute('applyNumberFormat', '1');
            if (baseXf.getAttribute('applyAlignment')) xf.setAttribute('applyAlignment', '1');
            if (useBorder) xf.setAttribute('applyBorder', '1');
            cellXfsEl.appendChild(xf);

            const newIndex = findElementsByLocalName(cellXfsEl, 'xf').length - 1;
            cellXfsEl.setAttribute('count', String(newIndex + 1));
            styleCache[cacheKey] = newIndex;
            return newIndex;
        }

        findElementsByLocalName(sheetRoot, 'row').forEach(function(rowEl) {
            const rowNum = parseInt(rowEl.getAttribute('r') || '0', 10);
            findElementsByLocalName(rowEl, 'c').forEach(function(cellEl) {
                const useBorder = !!rowNum && rowNum >= startRow;
                const useBold = !!boldRows[rowNum];
                if (!useBorder && !useBold) return;
                cellEl.setAttribute('s', String(getStyledCellXf(cellEl.getAttribute('s'), useBorder, useBold)));
            });
        });

        const serializer = new XMLSerializer();
        return {
            stylesXml: serializer.serializeToString(styleDoc),
            sheetXml: serializer.serializeToString(sheetDoc)
        };
    }

    function findWorksheetZipPath(zip) {
        return Object.keys(zip.files).find(function(name) {
            return /^xl\/worksheets\/sheet\d+\.xml$/.test(name);
        }) || 'xl/worksheets/sheet1.xml';
    }

    function downloadWorkbookWithBorders(workbook, filename, formatOptions) {
        if (typeof JSZip === 'undefined') {
            XLSX.writeFile(workbook, filename);
            return;
        }

        const data = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
        JSZip.loadAsync(data).then(function(zip) {
            const styleFile = 'xl/styles.xml';
            const sheetFile = findWorksheetZipPath(zip);
            if (!zip.file(styleFile) || !zip.file(sheetFile)) {
                XLSX.writeFile(workbook, filename);
                return null;
            }
            return Promise.all([
                zip.file(styleFile).async('string'),
                zip.file(sheetFile).async('string')
            ]).then(function(parts) {
                const updated = applyRevenueStatsExcelSheetFormatting(parts[0], parts[1], formatOptions || {});
                zip.file(styleFile, updated.stylesXml);
                zip.file(sheetFile, updated.sheetXml);
                return zip.generateAsync({
                    type: 'blob',
                    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
            }).then(function(blob) {
                if (!blob) return;
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });
        }).catch(function(err) {
            console.error('downloadWorkbookWithBorders:', err);
            XLSX.writeFile(workbook, filename);
        });
    }

    const app = Vue.createApp({
        data() {
            return {
                departments: [],
                selectedDepartment: null,
                pendingUrlDepartmentId: null,
                selectedMonth: currentYearMonth(),
                availableMonths: [],
                activeSubTab: 'summary',
                loading: false,
                loadingDepartments: false,
                showEstimatedAllTime: false,
                showCancelledAllTime: false,
                showCancelledEstimatedOnly: true,
                showTantouCaily: true,
                showTantouGuis: true,
                errorMessage: '',
                summaryByCompany: [],
                summaryTotals: emptySummaryTotals(),
                summaryByCompanyCaily: [],
                summaryTotalsCaily: emptySummaryTotals(),
                summaryByCompanyGuis: [],
                summaryTotalsGuis: emptySummaryTotals(),
                targetSummary: {
                    monthly_target_sales: 0,
                    cumulative_target_sales: 0,
                    monthly_actual_sales: 0,
                    cumulative_actual_sales: 0
                },
                estimatedProjects: [],
                invoicedProjects: [],
                completedUninvoiced: [],
                cancelledProjects: []
            };
        },
        computed: {
            monthLabel() {
                const found = (this.availableMonths || []).find(m => m.month === this.selectedMonth);
                return found && found.month_label ? found.month_label : buildMonthLabel(this.selectedMonth);
            },
            currentYearMonthValue() {
                return currentYearMonth();
            },
            estimatedGroups() {
                return groupProjectsByCompany(this.filteredEstimatedProjects, ['amount']);
            },
            invoicedGroups() {
                return groupProjectsByCompany(this.filteredInvoicedProjects, ['amount', 'invoice_amount']);
            },
            backlogGroups() {
                return groupProjectsByCompany(this.filteredBacklogProjects, ['amount']);
            },
            cancelledGroups() {
                return groupProjectsByCompany(this.filteredCancelledProjects, ['amount']);
            },
            filteredInvoicedProjects() {
                return this.filterProjectsByTantou(this.invoicedProjects);
            },
            filteredBacklogProjects() {
                return this.filterProjectsByTantou(this.completedUninvoiced);
            },
            filteredEstimatedProjects() {
                return this.filterProjectsByTantou(this.estimatedProjects);
            },
            filteredCancelledProjects() {
                return this.filterProjectsByTantou(this.cancelledProjects);
            },
            invoicedInvoiceAmountTotal() {
                return sumProjectField(this.filteredInvoicedProjects, 'invoice_amount');
            },
            backlogAmountTotal() {
                return sumProjectField(this.filteredBacklogProjects, 'amount');
            },
            estimatedAmountTotal() {
                return sumProjectField(this.filteredEstimatedProjects, 'amount');
            },
            cancelledAmountTotal() {
                return sumProjectField(this.filteredCancelledProjects, 'amount');
            },
            summaryTantouBlocks() {
                return [
                    {
                        tantou: 'CAILY',
                        rows: this.summaryByCompanyCaily,
                        totals: this.summaryTotalsCaily
                    },
                    {
                        tantou: 'GUIS',
                        rows: this.summaryByCompanyGuis,
                        totals: this.summaryTotalsGuis
                    }
                ];
            },
            summaryUnbilledTotals() {
                const projects = this.completedUninvoiced || [];
                const companySet = {};
                let amount = 0;
                projects.forEach(function(p) {
                    amount += Number(p.amount) || 0;
                    const name = (p.company_name && String(p.company_name).trim())
                        ? String(p.company_name).trim()
                        : '（未設定）';
                    companySet[name] = true;
                });
                return {
                    amount: amount,
                    project_count: projects.length,
                    company_count: Object.keys(companySet).length
                };
            },
            monthlyAchievementRate() {
                const target = Number(this.targetSummary.monthly_target_sales) || 0;
                const actual = Number(this.targetSummary.monthly_actual_sales) || 0;
                if (target <= 0) return 0;
                return (actual / target) * 100;
            },
            cumulativeAchievementRate() {
                const target = Number(this.targetSummary.cumulative_target_sales) || 0;
                const actual = Number(this.targetSummary.cumulative_actual_sales) || 0;
                if (target <= 0) return 0;
                return (actual / target) * 100;
            }
        },
        methods: {
            formatCompanyNameLabel,
            formatCurrency(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '¥0';
                return '¥' + Math.round(n).toLocaleString('ja-JP');
            },
            formatCount(val) {
                const n = Number(val);
                return Number.isFinite(n) ? String(Math.round(n)) : '0';
            },
            formatSummaryAmount(val) {
                const n = Number(val);
                if (!Number.isFinite(n) || Math.round(n) === 0) return '';
                return this.formatCurrency(n);
            },
            formatPercent(val) {
                const n = Number(val);
                if (!Number.isFinite(n)) return '0%';
                return Math.round(n).toLocaleString('ja-JP') + '%';
            },
            achievementRateBarClass(rate) {
                const n = Number(rate) || 0;
                if (n >= 100) return 'bg-success';
                if (n >= 70) return 'bg-primary';
                if (n >= 40) return 'bg-warning';
                return 'bg-danger';
            },
            achievementRateTextClass(rate) {
                const n = Number(rate) || 0;
                if (n >= 100) return 'text-success';
                if (n >= 70) return 'text-primary';
                if (n >= 40) return 'text-warning';
                return 'text-danger';
            },
            achievementBarWidth(rate) {
                const n = Math.max(0, Number(rate) || 0);
                return Math.min(100, n) + '%';
            },
            formatDate(val) {
                if (!val) return '—';
                const s = String(val).trim();
                if (!s || /^0000-00-00/.test(s)) return '—';
                return s.length >= 10 ? s.substring(0, 10) : s;
            },
            formatProjectType(p) {
                const parts = [p && p.parent_type1, p && p.parent_type2]
                    .map(function(v) { return v && String(v).trim(); })
                    .filter(Boolean);
                return parts.length ? parts.join(' / ') : '—';
            },
            formatProjectNouki(p) {
                if (!p) return '—';
                const tantou = String(p.tantou || '').trim();
                if (tantou === 'CAILY') return this.formatDate(p.caily_nouki);
                if (tantou === 'GUIS') return this.formatDate(p.guis_nouki);
                const caily = this.formatDate(p.caily_nouki);
                const guis = this.formatDate(p.guis_nouki);
                if (caily !== '—' && guis !== '—') return caily + ' / ' + guis;
                return caily !== '—' ? caily : guis;
            },
            formatProjectTeams(p) {
                const names = p && p.team_names ? String(p.team_names).trim() : '';
                return names || '—';
            },
            filterProjectsByTantou(projects) {
                const allowCaily = !!this.showTantouCaily;
                const allowGuis = !!this.showTantouGuis;
                if (allowCaily && allowGuis) return projects || [];
                if (!allowCaily && !allowGuis) return [];
                return (projects || []).filter(function(p) {
                    const tantou = String((p && p.tantou) || '').trim().toUpperCase();
                    if (tantou === 'CAILY') return allowCaily;
                    if (tantou === 'GUIS') return allowGuis;
                    return false;
                });
            },
            saveMonthToStorage() {
                try {
                    localStorage.setItem(STORAGE_KEY_MONTH, this.selectedMonth);
                } catch (e) {}
            },
            saveDepartmentToStorage(dept) {
                try {
                    if (dept && dept.id != null) {
                        localStorage.setItem(STORAGE_KEY_DEPT, JSON.stringify({ id: dept.id, name: dept.name }));
                    }
                } catch (e) {}
            },
            loadMonthFromStorage() {
                try {
                    const saved = localStorage.getItem(STORAGE_KEY_MONTH);
                    if (!saved || !/^\d{4}-\d{2}$/.test(saved)) return;

                    const allowed = (this.availableMonths || []).some(function(m) {
                        return m && m.month === saved;
                    });
                    if (allowed) {
                        this.selectedMonth = saved;
                    }
                } catch (e) {}
            },
            loadDepartmentFromStorage() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY_DEPT);
                    if (!raw) return null;
                    return JSON.parse(raw);
                } catch (e) {
                    return null;
                }
            },
            refreshAvailableMonths() {
                const map = {};
                buildMonthOptions(18, 12).forEach(function(m) {
                    if (m && m.month) map[m.month] = m;
                });
                if (this.selectedMonth && !map[this.selectedMonth]) {
                    map[this.selectedMonth] = {
                        month: this.selectedMonth,
                        month_label: buildMonthLabel(this.selectedMonth)
                    };
                }
                this.availableMonths = Object.values(map).sort(function(a, b) {
                    return String(b.month).localeCompare(String(a.month));
                });
            },
            readStateFromUrl() {
                try {
                    const params = new URLSearchParams(window.location.search || '');
                    const tab = normalizeSubTab(params.get('tab'));
                    const monthParam = String(params.get('month') || '').trim();
                    const departmentId = parseInt(String(params.get('department_id') || '').trim(), 10);
                    return {
                        tab: tab,
                        hasMonth: /^\d{4}-\d{2}$/.test(monthParam),
                        month: monthParam,
                        hasDepartmentId: Number.isFinite(departmentId) && departmentId > 0,
                        departmentId: departmentId
                    };
                } catch (e) {
                    return { tab: 'summary', hasMonth: false, month: '', hasDepartmentId: false, departmentId: null };
                }
            },
            syncStateToUrl() {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', normalizeSubTab(this.activeSubTab));
                    if (/^\d{4}-\d{2}$/.test(this.selectedMonth || '')) {
                        url.searchParams.set('month', this.selectedMonth);
                    } else {
                        url.searchParams.delete('month');
                    }
                    if (this.selectedDepartment && this.selectedDepartment.id != null) {
                        url.searchParams.set('department_id', String(this.selectedDepartment.id));
                    } else if (this.pendingUrlDepartmentId != null) {
                        url.searchParams.set('department_id', String(this.pendingUrlDepartmentId));
                    } else {
                        url.searchParams.delete('department_id');
                    }
                    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
                } catch (e) {}
            },
            setActiveSubTab(tab) {
                const nextTab = normalizeSubTab(tab);
                if (this.activeSubTab === nextTab) return;
                this.activeSubTab = nextTab;
                this.syncStateToUrl();
            },
            async departmentHasRevenueStatsAccess(departmentId) {
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                    return true;
                }
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'department',
                            method: 'get_user_permission_by_department',
                            department_id: departmentId
                        }
                    });
                    const perms = response.data || {};
                    return perms.project_director_stat == 1;
                } catch (e) {
                    return false;
                }
            },
            async filterDepartmentsForRevenueAccess(departments) {
                const list = (departments || []).filter(function(d) { return d && d.can_project != 0; });
                if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                    return list;
                }
                const checks = await Promise.all(list.map(async function(d) {
                    const allowed = await this.departmentHasRevenueStatsAccess(d.id);
                    return allowed ? d : null;
                }.bind(this)));
                return checks.filter(Boolean);
            },
            async loadDepartments() {
                this.loadingDepartments = true;
                try {
                    const response = await axios.get('/api/index.php?model=department&method=listByUser');
                    const all = response.data || [];
                    this.departments = await this.filterDepartmentsForRevenueAccess(all);
                    if (!this.departments.length) {
                        this.selectedDepartment = null;
                        this.syncStateToUrl();
                        return;
                    }
                    const urlState = this.pendingUrlDepartmentId != null
                        ? { hasDepartmentId: true, departmentId: this.pendingUrlDepartmentId }
                        : this.readStateFromUrl();
                    const urlDepartmentId = urlState.hasDepartmentId ? urlState.departmentId : null;
                    const saved = this.loadDepartmentFromStorage();
                    let dept = null;
                    if (urlDepartmentId != null) {
                        dept = this.departments.find(function(d) {
                            return d && String(d.id) === String(urlDepartmentId);
                        });
                    }
                    if (!dept && saved) {
                        dept = this.departments.find(function(d) {
                            return d && String(d.id) === String(saved.id);
                        });
                    }
                    if (!dept) {
                        dept = this.departments[0];
                    }
                    this.pendingUrlDepartmentId = null;
                    await this.selectDepartment(dept, false);
                } catch (err) {
                    console.error('loadDepartments:', err);
                    this.departments = [];
                    this.errorMessage = typeof translateText === 'function'
                        ? translateText('部署の読み込みに失敗しました。')
                        : '部署の読み込みに失敗しました。';
                } finally {
                    this.loadingDepartments = false;
                }
            },
            async selectDepartment(department, persist) {
                if (!department) {
                    this.selectedDepartment = null;
                    this.syncStateToUrl();
                    return;
                }
                if (persist !== false) {
                    this.saveDepartmentToStorage(department);
                }
                this.selectedDepartment = department;
                this.errorMessage = '';
                await this.loadStats();
                this.syncStateToUrl();
            },
            onMonthChange() {
                this.refreshAvailableMonths();
                this.saveMonthToStorage();
                this.syncStateToUrl();
                this.loadStats();
            },
            setSelectedMonth(month) {
                if (!month || !/^\d{4}-\d{2}$/.test(month)) return;
                if (this.selectedMonth === month) return;
                this.selectedMonth = month;
                this.refreshAvailableMonths();
                this.saveMonthToStorage();
                this.syncStateToUrl();
                this.loadStats();
            },
            goToPrevMonth() {
                this.setSelectedMonth(addMonthsToYearMonth(this.selectedMonth, -1));
            },
            goToNextMonth() {
                this.setSelectedMonth(addMonthsToYearMonth(this.selectedMonth, 1));
            },
            goToThisMonth() {
                this.setSelectedMonth(currentYearMonth());
            },
            onEstimatedAllTimeChange(checked) {
                this.showEstimatedAllTime = !!checked;
                this.loadStats();
            },
            onCancelledAllTimeChange(checked) {
                this.showCancelledAllTime = !!checked;
                this.loadStats();
            },
            onCancelledEstimatedOnlyChange(checked) {
                this.showCancelledEstimatedOnly = !!checked;
                this.loadStats();
            },
            getExportPeriodLabel(tabKey) {
                if (tabKey === 'backlog') {
                    return translateLabel('すべての期間');
                }
                if (tabKey === 'estimated' && this.showEstimatedAllTime) {
                    return translateLabel('すべての期間');
                }
                if (tabKey === 'cancelled' && this.showCancelledAllTime) {
                    return translateLabel('すべての期間');
                }
                return this.monthLabel || this.selectedMonth || '';
            },
            exportTabToExcel(tabKey) {
                if (typeof XLSX === 'undefined') {
                    const msg = translateLabel('Excel出力に失敗しました。');
                    if (typeof Notiflix !== 'undefined') {
                        Notiflix.Notify.failure(msg);
                    } else {
                        alert(msg);
                    }
                    return;
                }

                const config = REVENUE_EXPORT_CONFIG[tabKey];
                if (!config) return;

                const groups = this[config.groupsComputed] || [];
                if (!groups.length) return;

                const title = buildRevenueExportTitle(tabKey, this);
                const exportData = buildRevenueStatsExcelRows(groups, config, this, title);
                const rows = exportData.rows;
                const colCount = (config.headers || []).length;
                const worksheet = XLSX.utils.aoa_to_sheet(rows);
                const workbook = XLSX.utils.book_new();
                const sheetName = String(config.sheetName || 'Sheet1').substring(0, 31);
                XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);

                const deptName = (this.selectedDepartment && this.selectedDepartment.name) || 'department';
                const period = this.getExportPeriodLabel(tabKey);
                const filename = sanitizeExcelFilenamePart(deptName)
                    + '_' + sanitizeExcelFilenamePart(config.filenamePrefix)
                    + '_' + sanitizeExcelFilenamePart(period)
                    + '.xlsx';

                downloadWorkbookWithBorders(workbook, filename, {
                    borderStartRow: title ? 2 : 1,
                    titleMerge: title ? { row: 1, colCount: colCount } : null,
                    columnWidths: computeColumnWidths(rows, colCount, title ? 1 : 0),
                    boldRows: exportData.companyRowNumbers.concat(exportData.headerRowNumbers)
                });
            },
            async loadStats() {
                if (!this.selectedDepartment || !this.selectedMonth) return;
                this.loading = true;
                this.errorMessage = '';
                try {
                    const response = await axios.get('/api/index.php', {
                        params: {
                            model: 'project',
                            method: 'getMonthlyRevenueStats',
                            department_id: this.selectedDepartment.id,
                            month: this.selectedMonth,
                            estimated_all_time: this.showEstimatedAllTime ? 1 : 0,
                            cancelled_all_time: this.showCancelledAllTime ? 1 : 0,
                            cancelled_estimated_only: this.showCancelledEstimatedOnly ? 1 : 0
                        }
                    });
                    const data = response.data || {};
                    if (data.status === 'error') {
                        this.errorMessage = data.message || data.error || 'Error';
                        this.summaryByCompany = [];
                        this.summaryTotals = emptySummaryTotals();
                        this.summaryByCompanyCaily = [];
                        this.summaryTotalsCaily = emptySummaryTotals();
                        this.summaryByCompanyGuis = [];
                        this.summaryTotalsGuis = emptySummaryTotals();
                        this.targetSummary = {
                            monthly_target_sales: 0,
                            cumulative_target_sales: 0,
                            monthly_actual_sales: 0,
                            cumulative_actual_sales: 0
                        };
                        this.estimatedProjects = [];
                        this.invoicedProjects = [];
                        this.completedUninvoiced = [];
                        this.cancelledProjects = [];
                        return;
                    }
                    this.summaryByCompany = data.summary_by_company || [];
                    this.summaryTotals = Object.assign(emptySummaryTotals(), data.summary_totals || {});
                    this.summaryByCompanyCaily = data.summary_by_company_caily || [];
                    this.summaryTotalsCaily = Object.assign(emptySummaryTotals(), data.summary_totals_caily || {});
                    this.summaryByCompanyGuis = data.summary_by_company_guis || [];
                    this.summaryTotalsGuis = Object.assign(emptySummaryTotals(), data.summary_totals_guis || {});
                    this.targetSummary = Object.assign({
                        monthly_target_sales: 0,
                        cumulative_target_sales: 0,
                        monthly_actual_sales: 0,
                        cumulative_actual_sales: 0
                    }, data.meta && data.meta.target_summary ? data.meta.target_summary : {});
                    this.estimatedProjects = data.estimated_projects || [];
                    this.invoicedProjects = data.invoiced_projects || [];
                    this.completedUninvoiced = data.completed_uninvoiced || [];
                    this.cancelledProjects = data.cancelled_projects || [];
                } catch (err) {
                    console.error('loadStats:', err);
                    const msg = err.response && err.response.data
                        ? (err.response.data.message || err.response.data.error)
                        : null;
                    this.errorMessage = msg || (typeof translateText === 'function'
                        ? translateText('データの読み込みに失敗しました。')
                        : 'データの読み込みに失敗しました。');
                } finally {
                    this.loading = false;
                }
            }
        },
        mounted() {
            const urlState = this.readStateFromUrl();
            this.activeSubTab = urlState.tab;
            this.pendingUrlDepartmentId = urlState.hasDepartmentId ? urlState.departmentId : null;
            this.selectedMonth = urlState.hasMonth ? urlState.month : currentYearMonth();
            this.refreshAvailableMonths();
            if (!urlState.hasMonth) {
                this.loadMonthFromStorage();
                this.refreshAvailableMonths();
            }
            this.loadDepartments();
        }
    });

    window.revenueStatsApp = app.mount('#app');
})();
