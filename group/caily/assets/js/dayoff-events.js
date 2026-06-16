/**
 * CAILY dayoff events (groupware get_dayoff_all_api + GUIS user display).
 */
(function (global) {
  'use strict';

  const DEFAULT_DAYOFF_API_URL = 'https://group.caily.com.vn/api/index.php?type=get_dayoff_all_api&debug=1';

  let dayoffListCache = null;
  let dayoffUserDisplayMap = {};

  function getApiUrl() {
    return (typeof global.DAYOFF_API_URL !== 'undefined' && global.DAYOFF_API_URL)
      ? global.DAYOFF_API_URL
      : DEFAULT_DAYOFF_API_URL;
  }

  function shortenDisplayName(fullName) {
    const trimmed = String(fullName || '').trim();
    if (!trimmed) {
      return '';
    }
    const parts = trimmed.split(/[\s　]+/).filter(Boolean);
    if (parts.length > 1) {
      return parts[parts.length - 1];
    }
    return trimmed;
  }

  function getDayoffUserDisplayTitle(userid, options) {
    const opts = options || {};
    const key = userid || '';
    const info = dayoffUserDisplayMap[key];
    if (!info) {
      return key;
    }
    const fullName = (info.realname && String(info.realname).trim()) ? info.realname : key;
    const name = opts.shortTitle ? shortenDisplayName(fullName) : fullName;
    const teamName = info.team_name && String(info.team_name).trim();
    return teamName ? (name + '(' + teamName + ')') : name;
  }

  async function resolveDayoffUserDisplaysFromGuis(dayoffList) {
    const userids = [];
    (dayoffList || []).forEach(function (item) {
      const uid = item.userid;
      if (uid && userids.indexOf(uid) === -1) {
        userids.push(uid);
      }
    });
    if (!userids.length) {
      dayoffUserDisplayMap = {};
      return dayoffUserDisplayMap;
    }
    try {
      const response = await axios.get(
        '/api/index.php?model=user&method=resolveDisplayByUserids&userids=' + encodeURIComponent(userids.join(','))
      );
      if (response.status === 200 && response.data && response.data.map) {
        dayoffUserDisplayMap = response.data.map;
        return dayoffUserDisplayMap;
      }
    } catch (e) {
      console.warn('resolveDisplayByUserids', e);
    }
    dayoffUserDisplayMap = {};
    return dayoffUserDisplayMap;
  }

  function isDayoffAllDay(item) {
    return /^true$/i.test(String(item.allday || ''));
  }

  function dayoffEventOverlapsRange(eventStart, eventEnd, rangeStart, rangeEnd) {
    return eventStart.isBefore(rangeEnd) && eventEnd.isAfter(rangeStart);
  }

  /** API times are Vietnam (UTC+7); display in Japan (Asia/Tokyo). */
  function convertVnDateTimeToJapan(dateStr, timeStr) {
    const time = timeStr || '00:00';
    let jp;
    if (typeof moment.tz === 'function') {
      jp = moment.tz(dateStr + ' ' + time, 'YYYY-MM-DD HH:mm', 'Asia/Ho_Chi_Minh').tz('Asia/Tokyo');
    } else {
      jp = moment(dateStr + ' ' + time, 'YYYY-MM-DD HH:mm').add(2, 'hours');
    }
    return {
      date: jp.format('YYYY-MM-DD'),
      time: jp.format('HH:mm'),
      iso: jp.format('YYYY-MM-DDTHH:mm:ss')
    };
  }

  function parseVnTimeMinutes(timeStr) {
    const parts = String(timeStr || '00:00').split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const minutes = parseInt(parts[1], 10) || 0;
    return hours * 60 + minutes;
  }

  function appendDayoffLabel(title, label) {
    if (!label) {
      return title;
    }
    return title + ' ' + label;
  }

  /** Schedule page: classify half/full day off using Vietnam (GMT+7) times. */
  function getScheduleDayoffLabel(item) {
    if (isDayoffAllDay(item)) {
      return '全休';
    }
    const startMin = parseVnTimeMinutes(item.time_start);
    const endMin = parseVnTimeMinutes(item.time_end);
    const morningStart = 7 * 60;
    const noon = 12 * 60;
    const afternoonStart = 12 * 60;
    const workEnd = 17 * 60 + 30;
    if (startMin >= morningStart && endMin <= noon) {
      return '午前休';
    }
    if (startMin >= afternoonStart && endMin <= workEnd) {
      return '午後休';
    }
    return '';
  }

  function buildAllDayDayoffEvents(item, title, isOwn, endDate, extraProps) {
    const startDay = moment(item.date_start, 'YYYY-MM-DD');
    const endDay = moment(endDate || item.date_start, 'YYYY-MM-DD');
    const isMultiDay = startDay.format('YYYY-MM-DD') !== endDay.format('YYYY-MM-DD');
    const events = [];
    const cursor = startDay.clone();

    while (cursor.format('YYYY-MM-DD') <= endDay.format('YYYY-MM-DD')) {
      const dayOfWeek = cursor.day();
      if (isMultiDay && (dayOfWeek === 0 || dayOfWeek === 6)) {
        cursor.add(1, 'day');
        continue;
      }
      const dayStr = cursor.format('YYYY-MM-DD');
      events.push({
        id: 'dayoff-' + item.id + '-' + dayStr,
        title: title,
        start: dayStr,
        end: cursor.clone().add(1, 'day').format('YYYY-MM-DD'),
        allDay: true,
        displayPriority: 2,
        extendedProps: Object.assign({
          isOwn: isOwn,
          calendar: 'その他',
          type: 'dayoff',
          can_edit: 'false',
          dayoffId: item.id
        }, extraProps || {})
      });
      cursor.add(1, 'day');
    }

    return events;
  }

  function mapDayoffItemToEvents(item, options) {
    const opts = options || {};
    const isAllDay = isDayoffAllDay(item);
    const isOwn = String(item.userid || '') === String(typeof global.USER_ID !== 'undefined' ? global.USER_ID : '');
    const title = getDayoffUserDisplayTitle(item.userid, options);

    if (opts.shortTitle) {
      const scheduleLabel = getScheduleDayoffLabel(item);
      if (scheduleLabel) {
        const endDate = isDayoffAllDay(item) ? item.date_end : item.date_start;
        return buildAllDayDayoffEvents(item, appendDayoffLabel(title, scheduleLabel), isOwn, endDate, {
          dayoffLabel: scheduleLabel
        });
      }
    }

    if (isAllDay) {
      const eventTitle = opts.shortTitle ? appendDayoffLabel(title, '全休') : title;
      return buildAllDayDayoffEvents(item, eventTitle, isOwn, item.date_end, opts.shortTitle ? { dayoffLabel: '全休' } : null);
    }

    const vnTimeStart = (item.time_start && item.time_start !== '00:00') ? item.time_start : '00:00';
    const vnTimeEnd = (item.time_end && item.time_end !== '00:00') ? item.time_end : '23:59';
    const startJp = convertVnDateTimeToJapan(item.date_start || '', vnTimeStart);
    const endJp = convertVnDateTimeToJapan(item.date_end || item.date_start || '', vnTimeEnd);
    return [{
      id: 'dayoff-' + item.id,
      title: title,
      start: startJp.iso,
      end: endJp.iso,
      allDay: false,
      displayPriority: 2,
      extendedProps: {
        isOwn: isOwn,
        calendar: 'その他',
        type: 'dayoff',
        can_edit: 'false',
        timeLabel: startJp.time + ' - ' + endJp.time
      }
    }];
  }

  function filterDayoffEventsForRange(list, rangeStart, rangeEnd, options) {
    const rangeStartM = moment(rangeStart);
    const rangeEndM = moment(rangeEnd);
    const events = [];

    (list || []).forEach(function (item) {
      if (String(item.status) !== '1') {
        return;
      }
      mapDayoffItemToEvents(item, options).forEach(function (event) {
        const eventStart = moment(event.start);
        const eventEnd = moment(event.end);
        if (dayoffEventOverlapsRange(eventStart, eventEnd, rangeStartM, rangeEndM)) {
          events.push(event);
        }
      });
    });

    return events;
  }

  async function getDayoffList() {
    if (dayoffListCache) {
      return dayoffListCache;
    }
    const response = await axios.get(getApiUrl(), { withCredentials: true });
    if (response.status !== 200 || !response.data || !response.data.success) {
      if (response.data && typeof handleErrors === 'function') {
        handleErrors(response.data);
      }
      return [];
    }
    dayoffListCache = response.data.list || [];
    await resolveDayoffUserDisplaysFromGuis(dayoffListCache);
    return dayoffListCache;
  }

  async function fetchDayoffEventsForRange(rangeStart, rangeEnd, options) {
    const list = await getDayoffList();
    return filterDayoffEventsForRange(list, rangeStart, rangeEnd, options);
  }

  global.DayoffEvents = {
    getDayoffList: getDayoffList,
    filterDayoffEventsForRange: filterDayoffEventsForRange,
    fetchDayoffEventsForRange: fetchDayoffEventsForRange,
    invalidateCache: function () {
      dayoffListCache = null;
    }
  };
})(window);
