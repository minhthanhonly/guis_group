(function() {
    const STORAGE_KEY_MONTH = 'revenue_statistics_selected_month';
    const STORAGE_KEY_DEPT = 'revenue_statistics_selected_department';
    const VALID_SUB_TABS = ['summary', 'detail', 'backlog', 'estimated', 'unquoted', 'cancelled'];

    /** 種別 (決済明細 tag) arrives entity-encoded like every API string; show it as plain text. */
    function decodeTypeText(s) {
        s = s == null ? '' : String(s);
        if (s.indexOf('&') === -1) return s;
        return s.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
            .replace(/&#0?39;/g, "'").replace(/&amp;/g, '&');
    }

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

    function excelAmount(val) {
        const n = Number(val);
        return Number.isFinite(n) ? Math.round(n) : 0;
    }

    /**
     * 無償 label of a row for a tab, from the status of that tab's kind
     * (請求済み → 請求; 未請求 → 請求 or 見積; the others → 見積). '' when not 無償.
     */
    function revenueFreeLabel(p, tabKey) {
        if (!p) return '';
        if (tabKey === 'detail') {
            return p.invoice_status === '無償' ? '無償' : '';
        }
        if (tabKey === 'backlog') {
            if (p.invoice_status === '無償') return '請求無償';
            if (p.estimate_status === '無償') return '見積無償';
            return '';
        }
        return p.estimate_status === '無償' ? '無償' : '';
    }

    function buildRevenueExportTitle(tabKey, vm) {
        switch (tabKey) {
            case 'detail':
                return translateLabel('請求済み案件') + '（' + vm.monthLabel + '）';
            case 'backlog':
                return translateLabel('未請求案件(請求待ち・完了)') + '（' + translateLabel('すべての期間') + '）';
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
            case 'unquoted':
                return translateLabel('未見積案件') + '（' + translateLabel('すべての期間') + '）';
            default:
                return '';
        }
    }

    // ===== 月末締め Excel: one workbook, one sheet per tab (styled after the 月末締め workbooks) =====
    const XL_FONT = 'ＭＳ Ｐゴシック';
    const XL_BLUE = 'FF00B0F0';
    const XL_PINK = 'FFFFCCFF';
    const XL_YELLOW = 'FFFFFFCC';
    const XL_GRAY = 'FFD9D9D9';
    const XL_AMOUNT_FMT = '"¥"#,##0_);[Red]\\("¥"#,##0\\)';
    const XL_PERCENT_FMT = '0.0%';
    const XL_THIN = { style: 'thin' };
    const XL_BORDER = { top: XL_THIN, left: XL_THIN, bottom: XL_THIN, right: XL_THIN };

    /** List sheets in tab order: tab key, sheet name, groups computed. */
    const REVENUE_EXCEL_LIST_SHEETS = [
        { tab: 'detail', sheet: '請求済み案件', groups: 'invoicedGroups' },
        { tab: 'backlog', sheet: '未請求案件', groups: 'backlogGroups' },
        { tab: 'estimated', sheet: '見積済案件', groups: 'estimatedGroups' },
        { tab: 'unquoted', sheet: '未見積', groups: 'unquotedGroups' },
        { tab: 'cancelled', sheet: 'キャンセル案件', groups: 'cancelledGroups' }
    ];

    function xlStyle(cell, opts) {
        opts = opts || {};
        cell.font = Object.assign({ name: XL_FONT, size: opts.size || 10, bold: !!opts.bold }, opts.font || {});
        cell.border = XL_BORDER;
        cell.alignment = { vertical: 'middle', horizontal: opts.align || 'left', wrapText: !!opts.wrap };
        if (opts.fill) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: opts.fill } };
        if (opts.numFmt) cell.numFmt = opts.numFmt;
    }

    /** Merge r1:c1–r2:c2 (when more than one cell), set the value and style every cell of the range. */
    function xlMerge(ws, r1, c1, r2, c2, value, opts) {
        if (r1 !== r2 || c1 !== c2) ws.mergeCells(r1, c1, r2, c2);
        ws.getCell(r1, c1).value = value;
        for (let r = r1; r <= r2; r++) {
            for (let c = c1; c <= c2; c++) xlStyle(ws.getCell(r, c), opts);
        }
    }

    function xlColLetter(col) {
        let n = col;
        let s = '';
        while (n > 0) {
            const rem = (n - 1) % 26;
            s = String.fromCharCode(65 + rem) + s;
            n = Math.floor((n - 1) / 26);
        }
        return s;
    }

    function xlText(val) {
        const s = val == null ? '' : String(val).trim();
        return s === '' ? '—' : s;
    }

    /** Columns of a list sheet (after 会社 / NO), per tab; exactly one has amount: true. */
    function revenueExcelColumns(tabKey, vm) {
        const energy = !!vm.showEnergyFeeType;
        // 省エネ: 担当者 (the project's managers) instead of 担当, and no チーム.
        const energyDept = !!vm.isEnergyDepartment;
        const cols = [
            { h: 'ID', w: 7, align: 'center', v: function(p) { return Number(p.id) || p.id; } },
            { h: '支店名', w: 14, v: function(p) { return xlText(p.branch_name); } },
            { h: '案件名', w: 32, link: true, v: function(p) { return xlText(p.name); } },
            { h: '工事番号', w: 12, v: function(p) { return xlText(p.parent_construction_number); } },
            energyDept
                ? { h: '担当者', w: 16, v: function(p) { return xlText(p.manager_names); } }
                : { h: '担当', w: 8, align: 'center', v: function(p) { return xlText(p.tantou); } },
            { h: '受注形態', w: 10, v: function(p) { return xlText(p.project_order_type); } },
            { h: '規模', w: 10, v: function(p) { return xlText(p.parent_scale); } },
            { h: '期限日', w: 11, align: 'center', v: function(p) { return vm.formatDate(p.end_date); } }
        ];
        if (!energyDept) {
            cols.push({ h: 'チーム', w: 14, v: function(p) { return vm.formatProjectTeams(p); } });
        }
        if (energy) {
            cols.push({ h: '種類', w: 9, align: 'center', v: function(p) { return xlText(p.fee_type); } });
        }
        const amount = function(field) {
            return { h: field === 'invoice_amount' ? '請求金額' : '見積金額', w: 14, amount: true, field: field };
        };
        const note = { h: '決済備考', w: 30, v: function(p) { return xlText(p.payment_note); } };
        switch (tabKey) {
            case 'detail':
                cols.push({ h: '請求日', w: 11, align: 'center', v: function(p) { return vm.formatDate(p.invoice_date); } });
                cols.push(amount('invoice_amount'));
                break;
            case 'backlog':
                cols.push(amount('amount'));
                if (energy) cols.push({ h: '請求状況', w: 10, align: 'center', v: function(p) { return xlText(p.invoice_status); } });
                cols.push({ h: '完了日', w: 11, align: 'center', v: function(p) { return vm.formatDate(p.actual_end_date); } });
                break;
            case 'estimated':
                cols.push(amount('amount'));
                cols.push({ h: '見積日', w: 11, align: 'center', v: function(p) { return vm.formatDate(p.estimate_date); } });
                cols.push({ h: '見積状況', w: 10, align: 'center', v: function(p) { return xlText(p.estimate_status); } });
                break;
            case 'unquoted':
                cols.push({ h: '状態', w: 10, align: 'center', v: function(p) { return xlText(p.status_label); } });
                cols.push(amount('amount'));
                cols.push({ h: '見積状況', w: 10, align: 'center', v: function(p) { return xlText(p.estimate_status); } });
                break;
            case 'cancelled':
                cols.push(amount('amount'));
                break;
        }
        cols.push(note);
        return cols;
    }

    /**
     * One list sheet: title band (部署 / 月 / 一覧名), header row, then per company a block whose
     * 会社 cell is merged down column B, closed by a 合計 row (SUM formula); a 総合計 row at the end.
     */
    function addRevenueListSheet(workbook, def, vm, detailBaseUrl) {
        const ws = workbook.addWorksheet(translateLabel(def.sheet).substring(0, 31), {
            views: [{ state: 'frozen', ySplit: 4, showGridLines: false }],
            pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0, printTitlesRow: '4:4' }
        });
        const cols = revenueExcelColumns(def.tab, vm);
        const firstCol = 4; // A: margin, B: 会社, C: NO
        const lastCol = firstCol + cols.length - 1;
        const amountIdx = cols.findIndex(function(c) { return c.amount; });
        const amountField = cols[amountIdx].field;
        const amountCol = firstCol + amountIdx;
        const amountLetter = xlColLetter(amountCol);

        ws.getColumn(1).width = 2;
        ws.getColumn(2).width = 14;
        ws.getColumn(3).width = 5;
        cols.forEach(function(c, i) { ws.getColumn(firstCol + i).width = c.w; });

        const titleOpts = { size: 14, bold: true, fill: XL_BLUE, align: 'center' };
        xlMerge(ws, 2, 2, 3, 4, (vm.selectedDepartment && vm.selectedDepartment.name) || '', titleOpts);
        xlMerge(ws, 2, 5, 3, 6, translateLabel('月末締め'), titleOpts);
        xlMerge(ws, 2, 7, 3, lastCol, buildRevenueExportTitle(def.tab, vm) + vm.tantouFilterSuffix(),
            Object.assign({}, titleOpts, { align: 'left' }));

        const headOpts = { fill: XL_BLUE, align: 'center', bold: true };
        xlMerge(ws, 4, 2, 4, 2, translateLabel('会社'), headOpts);
        xlMerge(ws, 4, 3, 4, 3, 'NO', headOpts);
        cols.forEach(function(c, i) { xlMerge(ws, 4, firstCol + i, 4, firstCol + i, translateLabel(c.h), headOpts); });

        const groups = vm[def.groups] || [];
        let row = 5;
        if (!groups.length) {
            xlMerge(ws, row, 2, row, lastCol, translateLabel('データがありません'),
                { align: 'center', font: { color: { argb: 'FF808080' } } });
            return;
        }

        const subtotalCells = [];
        let projectCount = 0;
        groups.forEach(function(group) {
            const start = row;
            (group.projects || []).forEach(function(p, idx) {
                ws.getCell(row, 3).value = idx + 1;
                xlStyle(ws.getCell(row, 3), { align: 'center' });
                cols.forEach(function(c, i) {
                    const cell = ws.getCell(row, firstCol + i);
                    if (c.amount) {
                        const free = revenueFreeLabel(p, def.tab);
                        cell.value = free ? translateLabel(free) : excelAmount(p[c.field]);
                        xlStyle(cell, free ? { align: 'center' } : { align: 'right', numFmt: XL_AMOUNT_FMT });
                        return;
                    }
                    const val = c.v(p);
                    if (c.link && detailBaseUrl && p.id) {
                        cell.value = { text: String(val), hyperlink: detailBaseUrl + encodeURIComponent(p.id) };
                        xlStyle(cell, { font: { color: { argb: 'FF0563C1' }, underline: true } });
                    } else {
                        cell.value = val;
                        xlStyle(cell, { align: c.align });
                    }
                });
                row++;
                projectCount++;
            });
            const end = row - 1;
            xlMerge(ws, start, 2, end, 2, group.company_name, { fill: XL_BLUE, align: 'center', wrap: true });

            // 合計 row: label over 会社 … the column before the amount, SUM under the amount.
            const label = translateLabel('合計') + '（' + group.projects.length + translateLabel('件') + '）';
            xlMerge(ws, row, 2, row, amountCol - 1, label, { fill: XL_BLUE, align: 'right', bold: true });
            const sumCell = ws.getCell(row, amountCol);
            sumCell.value = {
                formula: 'SUM(' + amountLetter + start + ':' + amountLetter + end + ')',
                result: Number(group.totals[amountField]) || 0
            };
            xlStyle(sumCell, { align: 'right', bold: true, numFmt: XL_AMOUNT_FMT });
            for (let c = amountCol + 1; c <= lastCol; c++) xlStyle(ws.getCell(row, c), {});
            subtotalCells.push(amountLetter + row);
            row++;
        });

        row++;
        const grandTotal = groups.reduce(function(sum, g) { return sum + (Number(g.totals[amountField]) || 0); }, 0);
        const grandLabel = translateLabel('総合計') + '（' + projectCount + translateLabel('件') + '）';
        xlMerge(ws, row, 2, row, amountCol - 1, grandLabel, { fill: XL_YELLOW, align: 'right', bold: true });
        const grandCell = ws.getCell(row, amountCol);
        grandCell.value = { formula: subtotalCells.join('+'), result: grandTotal };
        xlStyle(grandCell, { fill: XL_YELLOW, align: 'right', bold: true, numFmt: XL_AMOUNT_FMT });
        for (let c = amountCol + 1; c <= lastCol; c++) xlStyle(ws.getCell(row, c), { fill: XL_YELLOW });
    }

    /**
     * まとめ sheet: 目標 / 実績 / 達成率 (月間・累計), then 請求 per company, 未請求分,
     * and (except 省エネ) 担当別 CAILY / GUIS blocks — like the まとめ sheet of the 月末締め workbooks.
     */
    function addRevenueSummarySheet(workbook, vm) {
        const ws = workbook.addWorksheet(translateLabel('まとめ'), {
            views: [{ showGridLines: false }],
            pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }
        });
        [2, 14, 18, 14, 16, 7, 7, 16].forEach(function(w, i) { ws.getColumn(i + 1).width = w; });

        const t = vm.targetSummary || {};
        ws.getCell('B2').value = (vm.selectedDepartment && vm.selectedDepartment.name) || '';
        ws.getCell('B2').font = { name: XL_FONT, size: 14, bold: true };
        ws.getCell('E2').value = vm.monthLabel + translateLabel('分');
        ws.getCell('E2').font = { name: XL_FONT, size: 14, bold: true };

        const pinkLabel = { fill: XL_PINK, align: 'center' };
        const pinkAmount = { fill: XL_PINK, align: 'right', numFmt: XL_AMOUNT_FMT };
        [
            { r: 3, tl: '月間目標売上', tv: t.monthly_target_sales, al: '月間実績売上', av: t.monthly_actual_sales },
            { r: 4, tl: '累計目標売上', tv: t.cumulative_target_sales, al: '累計実績売上', av: t.cumulative_actual_sales }
        ].forEach(function(x) {
            xlMerge(ws, x.r, 2, x.r, 2, translateLabel(x.tl), pinkLabel);
            xlMerge(ws, x.r, 3, x.r, 3, excelAmount(x.tv), pinkAmount);
            xlMerge(ws, x.r, 4, x.r, 4, translateLabel(x.al), pinkLabel);
            xlMerge(ws, x.r, 5, x.r, 5, excelAmount(x.av), pinkAmount);
            xlMerge(ws, x.r, 6, x.r, 7, translateLabel('達成率'), pinkLabel);
            const target = Number(x.tv) || 0;
            xlMerge(ws, x.r, 8, x.r, 8, {
                formula: 'IF(C' + x.r + '=0,0,E' + x.r + '/C' + x.r + ')',
                result: target > 0 ? (Number(x.av) || 0) / target : 0
            }, { fill: XL_PINK, align: 'center', numFmt: XL_PERCENT_FMT, bold: true });
            ws.getRow(x.r).height = 22;
        });

        const head = { fill: XL_YELLOW, align: 'center', bold: true };
        xlMerge(ws, 6, 2, 6, 4, '', head);
        xlMerge(ws, 6, 5, 6, 5, translateLabel('請求金額'), head);
        xlMerge(ws, 6, 6, 6, 7, translateLabel('請求件数'), head);
        xlMerge(ws, 6, 8, 6, 8, translateLabel('合計'), head);

        let row = 7;
        function block(label, rows) {
            const list = rows && rows.length ? rows : [{ company_name: '—', invoice_amount: 0, invoice_count: 0 }];
            const start = row;
            list.forEach(function(r) {
                xlMerge(ws, row, 3, row, 4, r.company_name, { fill: XL_BLUE, align: 'center' });
                xlMerge(ws, row, 5, row, 5, excelAmount(r.invoice_amount), { align: 'right', numFmt: XL_AMOUNT_FMT });
                xlMerge(ws, row, 6, row, 7, Number(r.invoice_count) || 0, { align: 'center' });
                xlStyle(ws.getCell(row, 8), { align: 'right', numFmt: XL_AMOUNT_FMT });
                row++;
            });
            const end = row - 1;
            xlMerge(ws, start, 2, end, 2, label, { fill: XL_YELLOW, align: 'center', wrap: true, bold: true });
            const total = list.reduce(function(s, r) { return s + (Number(r.invoice_amount) || 0); }, 0);
            const totalCell = ws.getCell(end, 8);
            totalCell.value = { formula: 'SUM(E' + start + ':E' + end + ')', result: total };
            xlStyle(totalCell, { align: 'right', bold: true, numFmt: XL_AMOUNT_FMT });
        }

        block(translateLabel('請求'), vm.summaryByCompany);

        const unbilled = vm.summaryUnbilledTotals || {};
        xlMerge(ws, row, 2, row, 4, translateLabel('未請求分'), { fill: XL_GRAY, align: 'center', bold: true });
        xlMerge(ws, row, 5, row, 5, excelAmount(unbilled.amount), { align: 'right', numFmt: XL_AMOUNT_FMT });
        xlMerge(ws, row, 6, row, 7, Number(unbilled.project_count) || 0, { align: 'center' });
        xlMerge(ws, row, 8, row, 8, { formula: 'E' + row, result: excelAmount(unbilled.amount) },
            { align: 'right', numFmt: XL_AMOUNT_FMT, bold: true });
        row++;

        if (!vm.isEnergyDepartment) {
            (vm.summaryTantouBlocks || []).forEach(function(b) {
                block(b.tantou + '\n' + translateLabel('担当別'), b.rows);
            });
        }

        row++;
        ws.getCell(row, 2).value = translateLabel('出力日時') + '：' + new Date().toLocaleString('ja-JP');
        ws.getCell(row, 2).font = { name: XL_FONT, size: 9, color: { argb: 'FF808080' } };
    }

    /** 2026年11月分_「部署」_月末締め.xlsx (the naming rule of the 月末締め workbooks). */
    function buildRevenueWorkbookFilename(vm) {
        const parts = String(vm.selectedMonth || '').split('-');
        const monthText = parts.length === 2
            ? (parts[0] + '年' + parseInt(parts[1], 10) + '月分')
            : String(vm.selectedMonth || '');
        const dept = (vm.selectedDepartment && vm.selectedDepartment.name) || 'department';
        return (monthText + '_「' + dept + '」_月末締め.xlsx').replace(/[\\/:*?"<>|]/g, '_');
    }

    async function exportRevenueWorkbook(vm) {
        const workbook = new ExcelJS.Workbook();
        workbook.created = new Date();
        const detailBaseUrl = window.location.origin
            + window.location.pathname.replace(/[^/]*$/, '') + 'detail.php?id=';

        addRevenueSummarySheet(workbook, vm);
        REVENUE_EXCEL_LIST_SHEETS.forEach(function(def) {
            addRevenueListSheet(workbook, def, vm, detailBaseUrl);
        });

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = buildRevenueWorkbookFilename(vm);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
    }

    const app = Vue.createApp({
        data() {
            return {
                departments: [],
                selectedDepartment: null,
                pendingUrlDepartmentId: null,
                selectedMonth: currentYearMonth(),
                availableMonths: [],
                dataMonths: [],
                // 月次締め
                monthClosing: null,
                closedMonths: [],
                canCloseMonth: false,
                closingBusy: false,
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
                cancelledProjects: [],
                unquotedProjects: [],
                exportingExcel: false,
                isEnergyDepartment: false
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
            // Month options grouped by year (newest first), with 今月 marked and
            // months that have 決済 data flagged, so the year is never ambiguous.
            monthGroups() {
                const current = currentYearMonth();
                const withData = {};
                (this.dataMonths || []).forEach(function(m) { withData[m] = true; });
                const closed = {};
                (this.closedMonths || []).forEach(function(m) { closed[m] = true; });
                const groups = [];
                const byYear = {};
                (this.availableMonths || []).forEach(function(m) {
                    const year = String(m.month).slice(0, 4);
                    if (!byYear[year]) {
                        byYear[year] = { year: year, label: year + '年', months: [] };
                        groups.push(byYear[year]);
                    }
                    let text = m.month_label || buildMonthLabel(m.month);
                    if (m.month === current) text += '（' + translateLabel('今月') + '）';
                    else if (m.month > current) text += '（' + translateLabel('先の月') + '）';
                    if (withData[m.month]) text = '● ' + text;
                    if (closed[m.month]) text += ' 🔒';
                    byYear[year].months.push({ month: m.month, text: text });
                });
                return groups;
            },
            isFutureMonth() {
                return !!this.selectedMonth && this.selectedMonth > currentYearMonth();
            },
            // 種類 column: always for 省エネ; elsewhere once a 決済明細 line carries a 種別
            // other than the default 設計費 (old data is all 設計費, so the column stays hidden).
            showEnergyFeeType() {
                if (this.isEnergyDepartment) return true;
                const lists = [this.invoicedProjects, this.estimatedProjects, this.completedUninvoiced, this.cancelledProjects];
                return lists.some(function(list) {
                    return (list || []).some(function(p) {
                        // '' = a line saved without 種別 (null = cancelled project without 見積)
                        return p.fee_type != null && p.fee_type !== '設計費';
                    });
                });
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
            unquotedGroups() {
                return groupProjectsByCompany(this.filteredUnquotedProjects, ['amount']);
            },
            filteredUnquotedProjects() {
                return this.filterProjectsByTantou(this.unquotedProjects);
            },
            unquotedAmountTotal() {
                return sumProjectField(this.filteredUnquotedProjects, 'amount');
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
                // 無償 rows leave nothing to invoice: not counted as 未請求.
                const projects = (this.completedUninvoiced || []).filter(function(p) {
                    return !revenueFreeLabel(p, 'backlog');
                });
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
            feeTypeBadgeClass(feeType) {
                if (feeType === '申請費') return 'bg-label-primary';
                if (feeType === '設計費') return 'bg-label-info';
                return 'bg-label-secondary';
            },
            freeLabel(p, tabKey) {
                return revenueFreeLabel(p, tabKey);
            },
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
            refreshAvailableMonths(extraMonths) {
                const map = {};
                // Only a few months ahead: a long run of future months at the top of the
                // list made it easy to pick next year's month by mistake.
                buildMonthOptions(18, 3).forEach(function(m) {
                    if (m && m.month) map[m.month] = m;
                });
                if (Array.isArray(extraMonths)) {
                    this.dataMonths = extraMonths.map(function(m) { return m && m.month; }).filter(Boolean);
                }
                (Array.isArray(extraMonths) ? extraMonths : []).forEach(function(m) {
                    if (!m || !m.month) return;
                    map[m.month] = {
                        month: m.month,
                        month_label: m.month_label || buildMonthLabel(m.month)
                    };
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
            /** Suffix of the list titles when the CAILY / GUIS filter hides one side. */
            tantouFilterSuffix() {
                if (this.showTantouCaily && this.showTantouGuis) return '';
                if (this.showTantouCaily) return '［CAILY］';
                if (this.showTantouGuis) return '［GUIS］';
                return '';
            },
            /** 月末締め Excel: one workbook, one sheet per tab. */
            async exportAllToExcel() {
                if (this.exportingExcel) return;
                const failMsg = translateLabel('Excel出力に失敗しました。');
                const fail = function() {
                    if (typeof Notiflix !== 'undefined') Notiflix.Notify.failure(failMsg); else alert(failMsg);
                };
                if (typeof ExcelJS === 'undefined') {
                    fail();
                    return;
                }
                this.exportingExcel = true;
                try {
                    await exportRevenueWorkbook(this);
                } catch (err) {
                    console.error('exportAllToExcel:', err);
                    fail();
                } finally {
                    this.exportingExcel = false;
                }
            },
            formatClosingAt(value) {
                const s = String(value || '');
                return s ? s.slice(0, 16).replace(/-/g, '/') : '';
            },
            /** 月次締め: close / reopen the selected month of the department (administrators). */
            async postClosing(method) {
                if (!this.selectedDepartment || !this.selectedMonth || this.closingBusy) return;
                this.closingBusy = true;
                try {
                    const formData = new FormData();
                    formData.append('department_id', this.selectedDepartment.id);
                    formData.append('month', this.selectedMonth);
                    const response = await axios.post('/api/index.php?model=project&method=' + method, formData);
                    const data = response.data || {};
                    if (data.status !== 'success') {
                        window.alert(data.message || data.error || translateLabel('保存に失敗しました。'));
                        return;
                    }
                    await this.loadStats();
                } catch (err) {
                    console.error(method + ':', err);
                    window.alert(translateLabel('保存に失敗しました。'));
                } finally {
                    this.closingBusy = false;
                }
            },
            closeMonth() {
                const msg = translateLabel('{month}を締めますか？\nこの月の発行済の請求は変更できなくなり、この月の日付で請求を入力することもできなくなります。').replace('{month}', this.monthLabel);
                if (!window.confirm(msg)) return;
                this.postClosing('closeRevenueMonth');
            },
            reopenMonth() {
                const msg = translateLabel('{month}の締めを解除しますか？\nこの月の請求を再び変更できるようになります。').replace('{month}', this.monthLabel);
                if (!window.confirm(msg)) return;
                this.postClosing('reopenRevenueMonth');
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
                        this.unquotedProjects = [];
                        this.isEnergyDepartment = false;
                        this.monthClosing = null;
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
                    this.isEnergyDepartment = !!(data.meta && Number(data.meta.is_energy_department) === 1);
                    this.monthClosing = data.meta && data.meta.closing ? data.meta.closing : null;
                    this.closedMonths = data.meta && Array.isArray(data.meta.closed_months) ? data.meta.closed_months : [];
                    this.canCloseMonth = !!(data.meta && Number(data.meta.can_close_month) === 1);
                    ['estimated_projects', 'invoiced_projects', 'completed_uninvoiced', 'cancelled_projects'].forEach(function(key) {
                        (data[key] || []).forEach(function(p) { if (p.fee_type) p.fee_type = decodeTypeText(p.fee_type); });
                    });
                    this.estimatedProjects = data.estimated_projects || [];
                    this.invoicedProjects = data.invoiced_projects || [];
                    this.completedUninvoiced = data.completed_uninvoiced || [];
                    this.cancelledProjects = data.cancelled_projects || [];
                    this.unquotedProjects = data.unquoted_projects || [];
                    // Always keep the default month window + current selection so the
                    // select never goes blank when a department has few/no invoice months.
                    this.refreshAvailableMonths(
                        data.meta && Array.isArray(data.meta.available_months)
                            ? data.meta.available_months
                            : []
                    );
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
