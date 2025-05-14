document.addEventListener('DOMContentLoaded', function() {
    const checkin = document.getElementById('checkin');
    const checkout = document.getElementById('checkout');

    if(checkin) {
        checkin.addEventListener('click', function() {
            doCheckin(checkin, checkout);
        });
    }

    if(checkout) {
        checkout.addEventListener('click', function() {
            doCheckout(checkout);
        });
    }
});

function showLoading(button) {
    $(button).prepend('<span class="spinner-grow me-1" role="status" aria-hidden="true"></span>');
    $(button).attr('disabled', true);
}

function hideLoading(button) {
    $(button).find('.spinner-grow').remove();
}

function doCheckin(button, checkout) {
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkin',
        data: {
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                
                if(response.data.timecard_id) {
                    $(checkout).attr('data-id', response.data.timecard_id);
                    $(checkout).attr('data-open', response.data.timecard_open);
                    $(checkout).attr('disabled', false);
                }
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}

function doCheckout(button) {
    const id = button.getAttribute('data-id');
    const open = button.getAttribute('data-open');
    showLoading(button);
    axios({
        method: 'post',
        url: '/api/index.php?model=timecard&method=checkout',
        data: {
            id: id,
            open: open,
            owner: USER_ID
        },
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
        .then(function (response) {
            if (response.status === 200 && response.data && response.data.status === 'success') {
                handleSuccess(response.data.message_code);
                $(button).attr('disabled', true);

                let result = '';

                if(response.data.timecard_time) {
                    result = 'お疲れ様でした！<br>勤務時間は' + response.data.timecard_time + 'です。';
                }

                if(response.data.timecard_timeover && response.data.timecard_timeover != '0:00') {
                    result += '<br>時間外は' + response.data.timecard_timeover + 'です。';
                }
                const $p = $(`<p class="text-success mb-0">${result}</p>`);
                $('#timecard-result').html($p);
            } else {
                handleErrors(response.data.message_code);
                $(button).prop('disabled', false);
            }
            hideLoading(button);
        }).catch(function (error) {
            handleErrors(error);
            console.log(error, error.response);
            hideLoading(button);
            $(button).prop('disabled', false);
        });
}

// 今週のスケジュール
document.addEventListener('DOMContentLoaded', async function () {
    const direction = isRtl ? 'rtl' : 'ltr';
    const calendarEl = document.getElementById('calendar');
    // Calendar settings
    const calendarColors = {
        '仕事': 'primary',
        '勤怠': 'warning',
        '休日': 'danger',
        '個人': 'info',
        'その他': 'success'
    };

    let currentStart = null;

    async function getEventList(start, end){
    eventList = [];
        const response = await axios.get(`/api/index.php?model=schedule&method=get_event&start=${start}&end=${end}`);
        // check if the response is successful
        if (response.status !== 200 || !response.data) {
            handleErrors(response.data);
        }
        return response.data;
    }

    async function fetchEvents(info, successCallback) {
      if (currentStart === null || moment(info.start).format('YYYY-MM-DD') !== currentStart) {
        currentStart = moment(info.start).format('YYYY-MM-DD');
        currentEvents = await getEventList(currentStart, moment(info.end).format('YYYY-MM-DD'));
      }
      successCallback(currentEvents);
    }

    let calendar = new Calendar(calendarEl, {
        locale: 'ja',
        initialView: 'listWeek',
        events: fetchEvents,
        plugins: [listPlugin],
        editable: false,
        dragScroll: true,
        dayMaxEvents: 4,
        defaultAllDay: true,
       
        firstDay: 1,
        headerToolbar: {
          start: 'title',
          end: 'prev,next, schedulePage'
        },
        titleFormat: function(date) {
          if (date.start && date.end) {
            // listWeekやweek view
            const start = moment(date.start).format('M月D日');
            // FullCalendarのendは次の日の0時なので1日前にする
            const end = moment(date.end).subtract(1, 'days').format('M月D日');
            return `今週のスケジュール: ${start} ～ ${end}`;
          } else if (date.date) {
            // day view
            return moment(date.date).format('YYYY年M月D日');
          } else if (date.start) {
            // month view
            return moment(date.start).format('YYYY年M月');
          }
          return '';
        },
        customButtons: {
          schedulePage: {
            text: 'もっと見る',
            click: function() {
              window.location.href = '/schedule';
            }
          }
        },
        allDayText: '終日',
        noEventsText: '予定がありません',
        buttonText: {
          today: '今日',
          month: '月',
          week: '週',
          day: '日',
        },
        direction: direction,
        initialDate: new Date(),
        showNonCurrentDates: false,
        //set height
        height: 'auto',
        // navLinks: true, // can click day/week names to navigate views
        eventClassNames: function ({ event: calendarEvent }) {
          const colorName = calendarColors[calendarEvent._def.extendedProps.calendar];
          // Background Color
          return ['bg-label-' + colorName];
        },
        
        eventDidMount: function(info) {
            if (info.event.extendedProps.public_level == 1) {
                const badge = document.createElement('span');
                badge.className = 'badge badge-pill bg-label-warning me-1';
                badge.innerHTML = '非公開';
                
                if (info.view.type === 'listWeek' || info.view.type === 'listMonth') {
                    const listEventEl = info.el.querySelector('.fc-list-event-title a');
                    if (listEventEl) {
                        listEventEl.insertAdjacentElement('beforebegin', badge);
                    }
                } else {
                    const titleEl = info.el.querySelector('.fc-event-title');
                    if (titleEl) {
                        titleEl.insertAdjacentElement('beforebegin', badge);
                    }
                }
            }
        },
        viewDidMount: function () {
            modifySchedulePageButton();
          },
      });
  
  
      
    // Render calendar
    calendar.render();
    modifySchedulePageButton();

    function modifySchedulePageButton() {
        const schedulePageButton = document.querySelector('.fc-schedulePage-button');
        schedulePageButton.classList.remove('fc-button-primary');
        schedulePageButton.classList.remove('fc-button');
        schedulePageButton.classList.add('btn', 'btn-primary', 'btn-sm');
    }

});