const YOUBI = ['日', '月', '火', '水', '木', '金', '土'];

function parseDateOnly(dateStr) {
  if (!dateStr) return null;
  let str = String(dateStr).trim();
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    str = str.slice(0, 10).replace(/-/g, '/');
  } else if (/^\d{4}\/\d{2}\/\d{2}/.test(str)) {
    str = str.slice(0, 10);
  }
  const d = new Date(str);
  return isNaN(d.getTime()) ? null : d;
}

export function formatPrintDate(dateStr) {
  const d = parseDateOnly(dateStr);
  if (!d) return dateStr ? String(dateStr) : '';
  const wd = YOUBI[d.getDay()];
  return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}(${wd})`;
}

export function formatPrintPeriod(start, end, sep = ' ~ ') {
  const a = formatPrintDate(start);
  const b = formatPrintDate(end);
  if (!a && !b) return '';
  if (!a) return b;
  if (!b) return a;
  return a + sep + b;
}

export function formatPrintDateTimeRange(date, startTime, endTime, suffix = '') {
  const datePart = formatPrintDate(date);
  if (!datePart) return '';
  if (startTime && endTime) {
    return `${datePart} ${startTime} ~ ${endTime}${suffix || ''}`;
  }
  if (startTime) return `${datePart} ${startTime}`;
  return datePart;
}

export function formatHolidayWorkBreakSuffix(breakTime) {
  if (breakTime === undefined || breakTime === null || breakTime === '') return '';
  const breakLabels = {
    '0.5': '0.5h', '1': '1h', '1.5': '1.5h', '2': '2h', '2.5': '2.5h', '3': '3h', '3.5': '3.5h', '4': '4h'
  };
  const minuteToHour = {
    30: '0.5h', 60: '1h', 90: '1.5h', 120: '2h', 150: '2.5h', 180: '3h', 210: '3.5h', 240: '4h'
  };
  const bt = String(breakTime);
  const label = breakLabels[bt] || minuteToHour[bt] || `${bt}h`;
  return `（休憩${label}）`;
}

export const printFormatMixin = {
  methods: {
    printDate(dateStr) {
      return formatPrintDate(dateStr);
    },
    printPeriod(start, end, sep) {
      return formatPrintPeriod(start, end, sep);
    },
    printDateTimeRange(date, startTime, endTime, suffix) {
      return formatPrintDateTimeRange(date, startTime, endTime, suffix);
    },
    printHolidayWorkDateTime() {
      const suffix = formatHolidayWorkBreakSuffix(this.formData.break_time);
      return formatPrintDateTimeRange(
        this.formData.date,
        this.formData.start_time,
        this.formData.end_time,
        suffix
      );
    }
  }
};
